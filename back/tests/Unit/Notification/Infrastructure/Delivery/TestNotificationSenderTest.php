<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Infrastructure\Delivery;

use App\Notification\Domain\Exception\TestNotificationConfigurationException;
use App\Notification\Infrastructure\Delivery\TestNotificationSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TestNotificationSenderTest extends TestCase
{
    private function twig(): Environment
    {
        return new Environment(new ArrayLoader([
            'emails/notification_test.html.twig' => '<p>Bonjour {{ displayName }}</p>',
        ]));
    }

    public function testTheEmailGoesFromTheAppAddressToTheUser(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email): bool {
                $this->assertSame('notifications@ziggytheque.fr', $email->getFrom()[0]->getAddress());
                $this->assertSame('user@example.com', $email->getTo()[0]->getAddress());
                $this->assertSame('Ziggytheque — Test de notification', $email->getSubject());
                $this->assertStringContainsString('Bonjour Alice, ceci est ton test.', (string) $email->getTextBody());
                $this->assertSame('<p>Bonjour Alice</p>', $email->getHtmlBody());

                return true;
            }));

        $sender = new TestNotificationSender($mailer, new MockHttpClient(), $this->twig(), 'notifications@ziggytheque.fr');
        $sender->sendEmail('user@example.com', 'Alice');
    }

    public function testTheDiscordMessageIsOneEmbedPostedToTheWebhook(): void
    {
        $response   = new MockResponse('', ['http_code' => 204]);
        $httpClient = new MockHttpClient($response);

        $sender = new TestNotificationSender(
            $this->createStub(MailerInterface::class),
            $httpClient,
            $this->twig(),
            'notifications@ziggytheque.fr',
        );
        $sender->sendDiscord('https://discord.com/api/webhooks/1/abc', 'Alice');

        $this->assertSame('POST', $response->getRequestMethod());
        $this->assertSame('https://discord.com/api/webhooks/1/abc', $response->getRequestUrl());
        /** @var array{embeds: list<array{description: string}>} $payload */
        $payload = json_decode((string) $response->getRequestOptions()['body'], true);
        $this->assertCount(1, $payload['embeds']);
        $this->assertSame('Bonjour Alice, ceci est ton test.', $payload['embeds'][0]['description']);
    }

    public function testARefusedWebhookIsAConfigurationProblem(): void
    {
        $sender = new TestNotificationSender(
            $this->createStub(MailerInterface::class),
            new MockHttpClient(new MockResponse('', ['http_code' => 404])),
            $this->twig(),
            'notifications@ziggytheque.fr',
        );

        $this->expectException(TestNotificationConfigurationException::class);
        $this->expectExceptionMessage('Discord webhook returned HTTP 404.');

        $sender->sendDiscord('https://discord.com/api/webhooks/1/deleted', 'Alice');
    }
}
