<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Infrastructure\ExternalApi\GoogleBooksDynamicLinksApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleBooksDynamicLinksApiClientTest extends TestCase
{
    private const string BASE_URL = 'https://books.google.com';

    private function makeClient(MockHttpClient $httpClient): GoogleBooksDynamicLinksApiClient
    {
        return new GoogleBooksDynamicLinksApiClient($httpClient, self::BASE_URL, new NullLogger());
    }

    private function makeIsbn(): Isbn
    {
        return Isbn::fromString('9782123456780');
    }

    private function jsonp(array $payload): string
    {
        return 'gbcb(' . json_encode($payload) . ');';
    }

    public function testFindByIsbnReturnsDtoWhenThumbnailPresent(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->jsonp([
                'ISBN:9782123456780' => [
                    'bib_key' => 'ISBN:9782123456780',
                    'thumbnail_url' => 'http://books.google.com/books/content?id=abc&printsec=frontcover&img=1&zoom=5&edge=curl',
                ],
            ])),
        ]);

        $result = $this->makeClient($httpClient)->findByIsbn($this->makeIsbn());

        $this->assertInstanceOf(MangaVolumeCoverDto::class, $result);
        $this->assertSame('google_books', $result->source);
        // http upgraded to https, page-curl removed, zoom bumped from 5 to 1
        $this->assertStringStartsWith('https://', $result->coverUrl);
        $this->assertStringNotContainsString('edge=curl', $result->coverUrl);
        $this->assertStringContainsString('zoom=1', $result->coverUrl);
        $this->assertStringNotContainsString('zoom=5', $result->coverUrl);
    }

    public function testFindByIsbnReturnsNullWhenNoThumbnail(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->jsonp([
                'ISBN:9782123456780' => ['bib_key' => 'ISBN:9782123456780', 'preview' => 'noview'],
            ])),
        ]);

        $result = $this->makeClient($httpClient)->findByIsbn($this->makeIsbn());

        $this->assertNull($result);
    }

    public function testFindByIsbnReturnsNullWhenIsbnNotInCatalog(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->jsonp([])),
        ]);

        $result = $this->makeClient($httpClient)->findByIsbn($this->makeIsbn());

        $this->assertNull($result);
    }

    public function testFindByIsbnReturnsNullOnHttpError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Server Error', ['http_code' => 500]),
        ]);

        $result = $this->makeClient($httpClient)->findByIsbn($this->makeIsbn());

        $this->assertNull($result);
    }

    public function testFindByIsbnReturnsNullOnMalformedBody(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('not jsonp at all'),
        ]);

        $result = $this->makeClient($httpClient)->findByIsbn($this->makeIsbn());

        $this->assertNull($result);
    }

    public function testFindByIsbnQueriesBibkeysWithCanonicalIsbn(): void
    {
        $requestedUrls = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrls): MockResponse {
            $requestedUrls[] = $url;
            return new MockResponse($this->jsonp([]));
        });

        $isbn = $this->makeIsbn();
        $this->makeClient($httpClient)->findByIsbn($isbn);

        $this->assertCount(1, $requestedUrls);
        $this->assertStringContainsString('bibkeys=ISBN:' . $isbn->value, urldecode($requestedUrls[0]));
        $this->assertStringContainsString('jscmd=viewapi', $requestedUrls[0]);
    }

    public function testFindByContextAlwaysReturnsNull(): void
    {
        $client = $this->makeClient(new MockHttpClient([]));

        $this->assertNull($client->findByContext('One Piece', null, 1));
    }
}
