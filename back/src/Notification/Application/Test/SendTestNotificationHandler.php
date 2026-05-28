<?php

declare(strict_types=1);

namespace App\Notification\Application\Test;

use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationRepositoryInterface;
use App\Notification\Domain\TestNotificationRecipient;
use App\Notification\Domain\TestNotificationRecipientResolverInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;
use Twig\Environment;

/**
 * Sends a one-off "test" notification to the user's configured channel so they
 * can verify their setup. Unlike regular notifications, a delivery failure
 * here is surfaced back to the user via a Notification entity — they need
 * actionable feedback to fix their preferences.
 *
 * The handler stays free of any Auth\Domain dependency: it resolves what it
 * needs via TestNotificationRecipientResolverInterface, whose implementation
 * lives in Auth\Infrastructure.
 */
#[AsMessageHandler]
final readonly class SendTestNotificationHandler
{
    private const CHANNEL_EMAIL      = 'email';
    private const CHANNEL_DISCORD    = 'discord';
    private const DISCORD_COLOR_BLUE = 3_447_003;

    public function __construct(
        private TestNotificationRecipientResolverInterface $recipientResolver,
        private NotificationRepositoryInterface $notificationRepository,
        private MailerInterface $mailer,
        private HttpClientInterface $httpClient,
        private Environment $twig,
        private LoggerInterface $logger,
        private string $notificationEmail,
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

        $html = $this->twig->render('emails/notification_test.html.twig', [
            'displayName' => $recipient->displayName,
        ]);

        $email = (new Email())
            ->from($this->notificationEmail)
            ->to($address)
            ->subject('Ziggytheque — Test de notification')
            ->text(sprintf('Bonjour %s, ceci est ton test.', $recipient->displayName))
            ->html($html);

        $this->mailer->send($email);
    }

    private function sendDiscordTest(TestNotificationRecipient $recipient): void
    {
        $webhook = $recipient->discordWebhookUrl;
        if ($webhook === null || $webhook === '') {
            throw new TestNotificationConfigurationException('No Discord webhook configured.');
        }

        $payload = [
            'embeds' => [[
                'title'       => '🔔 Test de notification',
                'description' => sprintf('Bonjour %s, ceci est ton test.', $recipient->displayName),
                'color'       => self::DISCORD_COLOR_BLUE,
                'footer'      => ['text' => 'Ziggytheque'],
                'timestamp'   => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            ]],
        ];

        $response = $this->httpClient->request('POST', $webhook, [
            'json'    => $payload,
            'timeout' => 5,
        ]);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new TestNotificationConfigurationException(
                sprintf('Discord webhook returned HTTP %d.', $status),
            );
        }
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
