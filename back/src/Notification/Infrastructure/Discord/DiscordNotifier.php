<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Discord;

use App\Notification\Domain\DiscordNotifierInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class DiscordNotifier implements DiscordNotifierInterface
{
    private const COLOR_GREEN  = 3_066_993;
    private const COLOR_ORANGE = 15_105_570;
    private const COLOR_RED    = 15_158_332;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private ?string $webhookUrl,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->webhookUrl !== null && $this->webhookUrl !== '';
    }

    /**
     * Rich embed: new articles found for a manga.
     *
     * @param array<int, array<string, mixed>> $articles
     */
    public function sendNewArticles(
        string $mangaTitle,
        ?string $mangaCoverUrl,
        int $count,
        array $articles,
    ): void {
        $fields = [];
        foreach (array_slice($articles, 0, 5) as $a) {
            $fields[] = [
                'name'   => mb_substr((string) $a['title'], 0, 256),
                'value'  => sprintf('[%s](%s)', $a['sourceName'], $a['url']),
                'inline' => false,
            ];
        }

        $embed = [
            'title'       => sprintf(
                '📰 %d nouveau%s article%s — %s',
                $count,
                $count > 1 ? 'x' : '',
                $count > 1 ? 's' : '',
                $mangaTitle,
            ),
            'color'       => self::COLOR_GREEN,
            'fields'      => $fields,
            'footer'      => ['text' => 'Ziggytheque'],
            'timestamp'   => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        ];

        if ($mangaCoverUrl !== null) {
            $embed['thumbnail'] = ['url' => $mangaCoverUrl];
        }

        $this->send(['embeds' => [$embed]]);
    }

    public function sendAlert(string $title, string $description, bool $critical = false): void
    {
        $this->send([
            'content' => $critical ? '@here' : null,
            'embeds'  => [[
                'title'       => ($critical ? '🚨 ' : '⚠️ ') . $title,
                'description' => mb_substr($description, 0, 4096),
                'color'       => $critical ? self::COLOR_RED : self::COLOR_ORANGE,
                'footer'      => ['text' => 'Ziggytheque Monitor'],
                'timestamp'   => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            ]],
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function send(array $payload): void
    {
        if (!$this->isConfigured() || $this->webhookUrl === null) {
            return;
        }

        try {
            $status = $this->httpClient->request('POST', $this->webhookUrl, [
                'json'    => $payload,
                'timeout' => 5,
            ])->getStatusCode();

            if ($status < 200 || $status >= 300) {
                $this->logger->warning('Discord webhook returned non-2xx', ['status' => $status]);
            }
        } catch (Throwable $e) {
            $this->logger->warning('Discord webhook failed', ['error' => $e->getMessage()]);
        }
    }
}
