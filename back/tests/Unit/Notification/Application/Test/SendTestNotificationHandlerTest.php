<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application\Test;

use App\Auth\Domain\NotificationChannelEnum;
use App\Auth\Domain\User;
use App\Notification\Application\Test\SendTestNotificationHandler;
use App\Notification\Application\Test\SendTestNotificationMessage;
use App\Notification\Domain\Exception\TestNotificationConfigurationException;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationRepositoryInterface;
use App\Notification\Domain\TestNotificationRecipient;
use App\Notification\Domain\TestNotificationRecipientResolverInterface;
use App\Notification\Domain\TestNotificationSenderInterface;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class SendTestNotificationHandlerTest extends TestCase
{
    private TestNotificationSenderInterface&MockObject $sender;
    private NotificationRepositoryInterface&MockObject $notificationRepository;
    private TestNotificationRecipient $recipient;

    protected function setUp(): void
    {
        $this->sender                 = $this->createMock(TestNotificationSenderInterface::class);
        $this->notificationRepository = $this->createMock(NotificationRepositoryInterface::class);
    }

    public function testEmailSentSilentlyOnSuccess(): void
    {
        $this->recipient = $this->emailRecipient('user@example.com');

        $this->sender->expects($this->once())->method('sendEmail')->with('user@example.com', 'Alice');
        $this->sender->expects($this->never())->method('sendDiscord');
        $this->notificationRepository->expects($this->never())->method('save');

        $this->handler()(new SendTestNotificationMessage('user-1'));
    }

    public function testEmailFailureSurfacesAsUserNotification(): void
    {
        $this->recipient = $this->emailRecipient('user@example.com');
        $this->sender->expects($this->once())->method('sendEmail')->willThrowException(new RuntimeException('SMTP unreachable'));

        $this->expectFailureNotification(function (Notification $notification): void {
            $this->assertSame($this->recipient->user, $notification->owner);
            $this->assertStringContainsString('email', $notification->message);
            $this->assertStringContainsString('SMTP unreachable', $notification->message);
        });

        $this->handler()(new SendTestNotificationMessage('user-1'));
    }

    public function testEmailMissingAddressCreatesFailureNotification(): void
    {
        $this->recipient = $this->emailRecipient(null);

        $this->sender->expects($this->never())->method('sendEmail');
        $this->expectFailureNotification(function (Notification $notification): void {
            $this->assertStringContainsString('No notification email configured.', $notification->message);
        });

        $this->handler()(new SendTestNotificationMessage('user-1'));
    }

    public function testDiscordSuccessSendsToWebhook(): void
    {
        $this->recipient = $this->discordRecipient('https://discord.com/api/webhooks/1/abc');

        $this->sender->expects($this->once())->method('sendDiscord')->with('https://discord.com/api/webhooks/1/abc', 'Alice');
        $this->sender->expects($this->never())->method('sendEmail');
        $this->notificationRepository->expects($this->never())->method('save');

        $this->handler()(new SendTestNotificationMessage('user-1'));
    }

    public function testDiscordRefusalSurfacesAsUserNotification(): void
    {
        $this->recipient = $this->discordRecipient('https://discord.com/api/webhooks/1/abc');
        $this->sender->expects($this->once())
            ->method('sendDiscord')
            ->willThrowException(new TestNotificationConfigurationException('Discord webhook returned HTTP 404.'));

        $this->expectFailureNotification(function (Notification $notification): void {
            $this->assertStringContainsString('Discord', $notification->message);
            $this->assertStringContainsString('HTTP 404', $notification->message);
        });

        $this->handler()(new SendTestNotificationMessage('user-1'));
    }

    public function testDiscordMissingWebhookCreatesFailureNotification(): void
    {
        $this->recipient = $this->discordRecipient(null);

        $this->sender->expects($this->never())->method('sendDiscord');
        $this->expectFailureNotification(function (Notification $notification): void {
            $this->assertStringContainsString('No Discord webhook configured.', $notification->message);
        });

        $this->handler()(new SendTestNotificationMessage('user-1'));
    }

    /**
     * Preferences saved before the URL was validated are still in the database,
     * so the webhook is re-checked here rather than trusted on read.
     */
    #[DataProvider('nonDiscordWebhooks')]
    public function testDiscordWebhookOutsideDiscordIsNeverRequested(string $webhook): void
    {
        $this->recipient = $this->discordRecipient($webhook);

        $this->sender->expects($this->never())->method('sendDiscord');
        $this->expectFailureNotification(function (Notification $notification): void {
            $this->assertStringContainsString('not a valid discord.com webhook URL', $notification->message);
        });

        $this->handler()(new SendTestNotificationMessage('user-1'));
    }

    /** @return iterable<string, array{string}> */
    public static function nonDiscordWebhooks(): iterable
    {
        yield 'cloud metadata'     => ['http://169.254.169.254/latest/meta-data/'];
        yield 'internal service'   => ['http://back:80/api/me'];
        yield 'lookalike host'     => ['https://discord.com.attacker.example/api/webhooks/1/t'];
        yield 'userinfo smuggling' => ['https://discord.com@attacker.example/api/webhooks/1/t'];
    }

    public function testAnUnknownChannelCreatesFailureNotification(): void
    {
        $this->recipient = new TestNotificationRecipient(
            user: $this->makeUser(NotificationChannelEnum::Email, null, null),
            displayName: 'Alice',
            channel: 'carrier-pigeon',
            notificationEmail: null,
            discordWebhookUrl: null,
        );

        $this->sender->expects($this->never())->method('sendEmail');
        $this->sender->expects($this->never())->method('sendDiscord');
        $this->expectFailureNotification(function (Notification $notification): void {
            $this->assertStringContainsString('Unknown channel "carrier-pigeon".', $notification->message);
        });

        $this->handler()(new SendTestNotificationMessage('user-1'));
    }

    public function testUnknownUserPropagatesNotFound(): void
    {
        $recipientResolver = $this->createStub(TestNotificationRecipientResolverInterface::class);
        $recipientResolver->method('resolve')->willThrowException(new NotFoundException('User', 'ghost'));
        $this->sender->expects($this->never())->method('sendEmail');
        $this->notificationRepository->expects($this->never())->method('save');

        $this->expectException(NotFoundException::class);

        (new SendTestNotificationHandler($recipientResolver, $this->notificationRepository, $this->sender, new NullLogger()))(
            new SendTestNotificationMessage('ghost'),
        );
    }

    /** @param callable(Notification): void $inspect */
    private function expectFailureNotification(callable $inspect): void
    {
        $this->notificationRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Notification $notification) use ($inspect): bool {
                $this->assertSame('test_failure', $notification->type);
                $inspect($notification);

                return true;
            }));
    }

    private function handler(): SendTestNotificationHandler
    {
        $recipientResolver = $this->createStub(TestNotificationRecipientResolverInterface::class);
        $recipientResolver->method('resolve')->willReturn($this->recipient);

        return new SendTestNotificationHandler(
            $recipientResolver,
            $this->notificationRepository,
            $this->sender,
            new NullLogger(),
        );
    }

    private function emailRecipient(?string $address): TestNotificationRecipient
    {
        return new TestNotificationRecipient(
            user: $this->makeUser(NotificationChannelEnum::Email, $address, null),
            displayName: 'Alice',
            channel: 'email',
            notificationEmail: $address,
            discordWebhookUrl: null,
        );
    }

    private function discordRecipient(?string $webhook): TestNotificationRecipient
    {
        return new TestNotificationRecipient(
            user: $this->makeUser(NotificationChannelEnum::Discord, null, $webhook),
            displayName: 'Alice',
            channel: 'discord',
            notificationEmail: null,
            discordWebhookUrl: $webhook,
        );
    }

    private function makeUser(
        NotificationChannelEnum $channel,
        ?string $notificationEmail,
        ?string $discordWebhookUrl,
    ): User {
        return new User(
            id: 'user-1',
            email: 'user@example.com',
            passwordHash: 'hash',
            displayName: 'Alice',
            notificationChannel: $channel,
            notificationEmail: $notificationEmail,
            discordWebhookUrl: $discordWebhookUrl,
        );
    }
}
