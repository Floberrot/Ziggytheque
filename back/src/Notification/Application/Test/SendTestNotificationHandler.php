<?php

declare(strict_types=1);

namespace App\Notification\Application\Test;

use App\Notification\Domain\Exception\TestNotificationConfigurationException;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationRepositoryInterface;
use App\Notification\Domain\TestNotificationRecipient;
use App\Notification\Domain\TestNotificationRecipientResolverInterface;
use App\Notification\Domain\TestNotificationSenderInterface;
use App\Shared\Domain\ValueObject\DiscordWebhookUrl;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Sends a one-off "test" notification to the user's configured channel so they
 * can verify their setup. Unlike regular notifications, a delivery failure
 * here is surfaced back to the user via a Notification entity — they need
 * actionable feedback to fix their preferences.
 *
 * The handler stays free of any Auth\Domain dependency: it resolves what it
 * needs via TestNotificationRecipientResolverInterface, whose implementation
 * lives in Auth\Infrastructure. The delivery itself (mailer, Discord HTTP call)
 * sits behind TestNotificationSenderInterface.
 */
#[AsMessageHandler]
final readonly class SendTestNotificationHandler
{
    private const CHANNEL_EMAIL   = 'email';
    private const CHANNEL_DISCORD = 'discord';

    public function __construct(
        private TestNotificationRecipientResolverInterface $recipientResolver,
        private NotificationRepositoryInterface $notificationRepository,
        private TestNotificationSenderInterface $sender,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendTestNotificationMessage $message): void
    {
        $recipient = $this->recipientResolver->resolve($message->userId);

        try {
            match ($recipient->channel) {
                self::CHANNEL_EMAIL   => $this->sendEmailTest($recipient),
                self::CHANNEL_DISCORD => $this->sendDiscordTest($recipient),
                default               => throw new TestNotificationConfigurationException(
                    sprintf('Unknown channel "%s".', $recipient->channel),
                ),
            };
        } catch (Throwable $exception) {
            $this->logger->warning('Test notification delivery failed', [
                'user_id' => $message->userId,
                'channel' => $recipient->channel,
                'error'   => $exception->getMessage(),
            ]);

            $this->notifyUserOfFailure($recipient, $exception);
        }
    }

    private function sendEmailTest(TestNotificationRecipient $recipient): void
    {
        $address = $recipient->notificationEmail;
        if ($address === null || $address === '') {
            throw new TestNotificationConfigurationException('No notification email configured.');
        }

        $this->sender->sendEmail($address, $recipient->displayName);
    }

    private function sendDiscordTest(TestNotificationRecipient $recipient): void
    {
        $webhook = $recipient->discordWebhookUrl;
        if ($webhook === null || $webhook === '') {
            throw new TestNotificationConfigurationException('No Discord webhook configured.');
        }

        // Re-checked at send time, not only when the preference was saved: rows
        // stored before the URL was validated must not become an SSRF vector.
        if (!DiscordWebhookUrl::isValid($webhook)) {
            throw new TestNotificationConfigurationException(
                'Configured Discord webhook is not a valid discord.com webhook URL.',
            );
        }

        $this->sender->sendDiscord($webhook, $recipient->displayName);
    }

    private function notifyUserOfFailure(TestNotificationRecipient $recipient, Throwable $exception): void
    {
        $channelLabel = $recipient->channel === self::CHANNEL_DISCORD ? 'Discord' : 'email';

        $notification = new Notification(
            id: Uuid::v4()->toRfc4122(),
            type: 'test_failure',
            message: sprintf(
                'Le test de notification %s a échoué : %s',
                $channelLabel,
                $exception->getMessage(),
            ),
            owner: $recipient->user,
        );

        $this->notificationRepository->save($notification);
    }
}
