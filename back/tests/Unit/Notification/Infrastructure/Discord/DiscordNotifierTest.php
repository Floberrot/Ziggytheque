<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Infrastructure\Discord;

use App\Notification\Infrastructure\Discord\DiscordNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** The daily summary as Discord receives it: one embed per series, never a crash. */
final class DiscordNotifierTest extends TestCase
{
    private const string WEBHOOK = 'https://discord.com/api/webhooks/1/token';

    /** @var list<array{string, string, array<string, mixed>}> */
    private array $requests = [];

    /** @var list<string> */
    private array $warnings = [];

    public function testSendsOneEmbedPerSeriesWithTheDayTotals(): void
    {
        $this->notifier(new MockResponse('', ['http_code' => 204]))->sendSchedulerSummary([
            [
                'mangaTitle'    => 'One Piece',
                'mangaCoverUrl' => 'https://cover.example/op.jpg',
                'articles'      => [
                    ['title' => 'One Piece 110', 'url' => 'https://news.example/1'],
                    ['title' => 'One Piece anime', 'url' => 'https://news.example/2'],
                ],
            ],
            [
                'mangaTitle'    => 'Naruto',
                'mangaCoverUrl' => null,
                'articles'      => [['title' => 'Naruto returns', 'url' => 'https://news.example/3']],
            ],
        ]);

        $this->assertCount(1, $this->requests);
        [$method, $url, $payload] = $this->requests[0];
        $this->assertSame('POST', $method);
        $this->assertSame(self::WEBHOOK, $url);
        $this->assertSame('📰 **Récap du jour** — 2 mangas · 3 articles', $payload['content']);

        $this->assertCount(2, $payload['embeds']);
        $this->assertSame('One Piece', $payload['embeds'][0]['title']);
        $this->assertSame(
            "• [One Piece 110](https://news.example/1)\n• [One Piece anime](https://news.example/2)",
            $payload['embeds'][0]['description'],
        );
        $this->assertSame(['url' => 'https://cover.example/op.jpg'], $payload['embeds'][0]['thumbnail']);
        $this->assertArrayNotHasKey('footer', $payload['embeds'][0]);
        $this->assertArrayNotHasKey('thumbnail', $payload['embeds'][1]);
        $this->assertSame(['text' => 'Ziggytheque'], $payload['embeds'][1]['footer']);
        $this->assertSame([], $this->warnings);
    }

    public function testKeepsWithinDiscordLimits(): void
    {
        $series = [];
        for ($index = 1; $index <= 12; $index++) {
            $articles = [];
            for ($article = 1; $article <= 10; $article++) {
                $articles[] = ['title' => str_repeat('t', 150), 'url' => 'https://news.example/' . $index . '/' . $article];
            }
            $series[] = ['mangaTitle' => 'Series ' . $index, 'mangaCoverUrl' => null, 'articles' => $articles];
        }

        $this->notifier(new MockResponse('', ['http_code' => 204]))->sendSchedulerSummary($series);

        $payload = $this->requests[0][2];
        // 10 series at most, 8 articles each, titles cut at 100 characters.
        $this->assertCount(10, $payload['embeds']);
        $this->assertSame(8, substr_count($payload['embeds'][0]['description'], '• ['));
        $this->assertStringContainsString('[' . str_repeat('t', 100) . ']', $payload['embeds'][0]['description']);
        $this->assertSame('📰 **Récap du jour** — 10 mangas · 100 articles', $payload['content']);
    }

    public function testSendsNothingWithoutAWebhook(): void
    {
        $notifier = new DiscordNotifier(new MockHttpClient($this->recorder(new MockResponse(''))), $this->logger(), '');

        $notifier->sendSchedulerSummary([
            ['mangaTitle' => 'One Piece', 'mangaCoverUrl' => null, 'articles' => []],
        ]);

        $this->assertFalse($notifier->isConfigured());
        $this->assertSame([], $this->requests);
    }

    public function testARefusedWebhookIsOnlyLogged(): void
    {
        $this->notifier(new MockResponse('', ['http_code' => 404]))->sendSchedulerSummary([
            ['mangaTitle' => 'One Piece', 'mangaCoverUrl' => null, 'articles' => []],
        ]);

        $this->assertSame(['Discord webhook returned non-2xx'], $this->warnings);
    }

    public function testAnUnreachableDiscordIsOnlyLogged(): void
    {
        $this->notifier(new MockResponse('', ['error' => 'Could not resolve host']))->sendSchedulerSummary([
            ['mangaTitle' => 'One Piece', 'mangaCoverUrl' => null, 'articles' => []],
        ]);

        $this->assertSame(['Discord webhook failed'], $this->warnings);
    }

    private function notifier(MockResponse $response): DiscordNotifier
    {
        return new DiscordNotifier(new MockHttpClient($this->recorder($response)), $this->logger(), self::WEBHOOK);
    }

    private function recorder(MockResponse $response): callable
    {
        return function (string $method, string $url, array $options) use ($response): MockResponse {
            $this->requests[] = [$method, $url, (array) json_decode((string) $options['body'], true)];

            return $response;
        };
    }

    private function logger(): AbstractLogger
    {
        $test = $this;

        return new class ($test) extends AbstractLogger {
            public function __construct(private readonly DiscordNotifierTest $test)
            {
            }

            /** @param array<mixed> $context */
            public function log($level, string|Stringable $message, array $context = []): void
            {
                if ($level === 'warning') {
                    $this->test->recordWarning((string) $message);
                }
            }
        };
    }

    public function recordWarning(string $message): void
    {
        $this->warnings[] = $message;
    }
}
