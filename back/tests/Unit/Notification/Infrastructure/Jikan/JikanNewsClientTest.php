<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Infrastructure\Jikan;

use App\Notification\Domain\Service\JikanNewsItem;
use App\Notification\Infrastructure\Jikan\JikanNewsClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

final class JikanNewsClientTest extends TestCase
{
    /** @var list<string> */
    private array $requestedUrls = [];

    public function testReadsTheNewsOfTheSeries(): void
    {
        $newsItems = $this->client((string) json_encode(['data' => [
            [
                'url'             => 'https://myanimelist.net/news/1',
                'title'           => 'One Piece anime returns',
                'excerpt'         => 'The Egghead arc.',
                'author_username' => 'mal-editor',
                'date'            => '2026-05-01T10:00:00+00:00',
            ],
            ['title' => 'No link, no author, no date'],
            'not a news',
        ]]))->fetchNews('13');

        $this->assertSame(['https://api.jikan.moe/v4/manga/13/news'], $this->requestedUrls);
        $this->assertEquals(
            [
                new JikanNewsItem(
                    'https://myanimelist.net/news/1',
                    'One Piece anime returns',
                    'The Egghead arc.',
                    'mal-editor',
                    '2026-05-01T10:00:00+00:00',
                ),
                new JikanNewsItem(null, 'No link, no author, no date', '', null, null),
            ],
            $newsItems,
        );
    }

    public function testASeriesWithoutNewsHasNone(): void
    {
        $this->assertSame([], $this->client('{"data": []}')->fetchNews('13'));
        $this->assertSame([], $this->client('{}')->fetchNews('13'));
    }

    /** The handler fails every followed copy's line and the worker retries the message. */
    public function testAnErrorStatusFailsTheDownload(): void
    {
        $this->expectException(ExceptionInterface::class);

        $this->client('{"status": 429}', 429)->fetchNews('13');
    }

    private function client(string $body, int $statusCode = 200): JikanNewsClient
    {
        return new JikanNewsClient(new MockHttpClient(function (string $method, string $url) use ($body, $statusCode): MockResponse {
            $this->requestedUrls[] = $url;

            return new MockResponse($body, ['http_code' => $statusCode]);
        }));
    }
}
