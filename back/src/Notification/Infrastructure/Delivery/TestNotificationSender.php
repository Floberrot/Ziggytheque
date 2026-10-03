<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Delivery;

use App\Notification\Domain\Exception\TestNotificationConfigurationException;
use App\Notification\Domain\TestNotificationSenderInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;

final readonly class TestNotificationSender implements TestNotificationSenderInterface
{
    private const int DISCORD_COLOR_BLUE = 3_447_003;

    public function __construct(
        private MailerInterface $mailer,
        private HttpClientInterface $httpClient,
        private Environment $twig,
        private string $notificationEmail,
    ) {
    }

    public function sendEmail(string $address, string $displayName): void
    {
        $email = (new Email())
            ->from($this->notificationEmail)
            ->to($address)
            ->subject('Ziggytheque — Test de notification')
            ->text(sprintf('Bonjour %s, ceci est ton test.', $displayName))
            ->html($this->twig->render('emails/notification_test.html.twig', ['displayName' => $displayName]));

        $this->mailer->send($email);
    }

    public function sendDiscord(string $webhookUrl, string $displayName): void
    {
        $payload = [
            'embeds' => [[
                'title'       => '🔔 Test de notification',
                'description' => sprintf('Bonjour %s, ceci est ton test.', $displayName),
                'color'       => self::DISCORD_COLOR_BLUE,
                'footer'      => ['text' => 'Ziggytheque'],
                'timestamp'   => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            ]],
        ];

        $status = $this->httpClient->request('POST', $webhookUrl, [
            'json'    => $payload,
            'timeout' => 5,
        ])->getStatusCode();

        // A deleted webhook answers 404: the user has to paste a new one.
        if ($status < 200 || $status >= 300) {
            throw new TestNotificationConfigurationException(sprintf('Discord webhook returned HTTP %d.', $status));
        }
    }
}
