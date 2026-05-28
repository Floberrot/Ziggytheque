<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application\Test;

use App\Auth\Domain\NotificationChannelEnum;
use App\Auth\Domain\User;
use App\Notification\Application\Test\SendTestNotificationHandler;
use App\Notification\Application\Test\SendTestNotificationMessage;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationRepositoryInterface;
use App\Notification\Domain\TestNotificationRecipient;
use App\Notification\Domain\TestNotificationRecipientResolverInterface;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface as MailerTransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Twig\Environment;

#[AllowMockObjectsWithoutExpectations]
final class SendTestNotificationHandlerTest extends TestCase
{
    private TestNotificationRecipientResolverInterface&MockObject $recipientResolver;
    private NotificationRepositoryInterface&MockObject $notificationRepository;
    private MailerInterface&MockObject $mailer;
    private HttpClientInterface&MockObject $httpClient;
    private Environment&MockObject $twig;

    protected function setUp(): void
    {
        $this->recipientResolver      = $this->createMock(TestNotificationRecipientResolverInterface::class);
        $this->notificationRepository = $this->createMock(NotificationRepositoryInterface::class);
        $this->mailer                 = $this->createMock(MailerInterface::class);
        $this->httpClient             = $this->createMock(HttpClientInterface::class);
        $this->twig                   = $this->createMock(Environment::class);

        $this->twig->method('render')->willReturn('<html>body</html>');
    }

    public function testEmailSentSilentlyOnSuccess(): void
    {
        $this->recipientResolver->method('resolve')
            ->willReturn($this->emailRecipient('user@example.com'));

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email): bool {
                $this->assertSame('Ziggytheque — Test de notification', $email->getSubject());
                $this->assertNotEmpty($email->getTo());
                $this->assertSame('user@example.com', $email->getTo()[0]->getAddress());
                $this->assertStringContainsString('ceci est ton test', (string) $email->getTextBody());
                return true;
            }));

        $this->notificationRepository->expects($this->never())->method('save');

        $this->handler()(new SendTestNotificationMessage('user-1'));
    }

    public function testEmailFailureSurfacesAsUserNotification(): void
    {
        $recipient = $this->emailRecipient('user@example.com');
        $this->recipientResolver->method('resolve')->willReturn($recipient);
        $this->mailer->method('send')->willThrowException($this->mailerException());

        $this->notificationRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Notification $notif) use ($recipient): bool {
                $this->assertSame('test_failure', $notif->type);
                $this->assertSame($recipient->user, $notif->owner);
                $this->assertStringContainsString('email', $notif->message);
                return true;
            }));

        $this->handler()(new SendTestNotificationMessage('user-1'));
    }

    public function testEmailMissingAddressCreatesFailureNotification(): void
    {
        $this->recipientResolver->method('resolve')->willReturn($this->emailRecipient(null));

        $this->mailer->expects($this->never())->method('send');
        $this->notificationRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Notification $notif): bool {
                $this->assertSame('test_failure', $notif->type);
                $this->assertStringContainsString('email', $notif->message);
                return true;
            }));

        $this->handler()(new SendTestNotificationMessage('user-1'));
    }

    public function testDiscordSuccessSendsToWebhook(): void
    {
        $this->recipientResolver->method('resolve')
            ->willReturn($this->discordRecipient('https://discord.com/api/webhooks/1/abc'));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(204);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://discord.com/api/webhooks/1/abc', $this->callback(function (array $opts): bool {
                $this->assertArrayHasKey('json', $opts);
                $this->assertArrayHasKey('embeds', $opts['json']);
                return true;
            }))
            ->willReturn($response);

        $this->notificationRepository->expects($this->never())->method('save');

        $this->handler()(new SendTestNotificationMessage('user-1'));
    }

    public function testDiscordNon2xxSurfacesAsUserNotification(): void
    {
        $this->recipientResolver->method('resolve')
            ->willReturn($this->discordRecipient('https://discord.com/api/webhooks/1/abc'));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);
        $this->httpClient->method('request')->willReturn($response);

        $this->notificationRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Notification $notif): bool {
                $this->assertSame('test_failure', $notif->type);
                $this->assertStringContainsString('Discord', $notif->message);
                return true;
            }));

        $this->handler()(new SendTestNotificationMessage('user-1'));
    }

    public function testDiscordMissingWebhookCreatesFailureNotification(): void
    {
        $this->recipientResolver->method('resolve')->willReturn($this->discordRecipient(null));

        $this->httpClient->expects($this->never())->method('request');
        $this->notificationRepository->expects($this->once())->method('save');

        $this->handler()(new SendTestNotificationMessage('user-1'));
    }

    public function testUnknownUserPropagatesNotFound(): void
    {
        $this->recipientResolver->method('resolve')
            ->willThrowException(new NotFoundException('User', 'ghost'));

        $this->expectException(NotFoundException::class);

        $this->handler()(new SendTestNotificationMessage('ghost'));
    }

    private function handler(): SendTestNotificationHandler
    {
        return new SendTestNotificationHandler(
            $this->recipientResolver,
            $this->notificationRepository,
            $this->mailer,
            $this->httpClient,
            $this->twig,
            new NullLogger(),
            'notifications@ziggytheque.fr',
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

    private function mailerException(): MailerTransportException
    {
        return new class extends RuntimeException implements MailerTransportException {
            public function __construct()
            {
                parent::__construct('SMTP unreachable');
            }

            public function getDebug(): string
            {
                return '';
            }

            public function appendDebug(string $debug): void
            {
            }
        };
    }
}
