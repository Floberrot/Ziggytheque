<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Infrastructure\ExternalApi\GoogleBooksMangaApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleBooksMangaApiClientTest extends TestCase
{
    private const string API_KEY = 'test-api-key';

    private function makeClient(MockHttpClient $httpClient): GoogleBooksMangaApiClient
    {
        return new GoogleBooksMangaApiClient($httpClient, self::API_KEY, new NullLogger());
    }

    private function makeIsbn(): Isbn
    {
        return Isbn::fromString('9782123456780');
    }

    private function volumeItemWithCover(string $coverUrl = 'https://books.google.com/cover.jpg'): array
    {
        return [
            'id' => 'google-vol-1',
            'volumeInfo' => [
                'title' => 'One Piece T.1',
                'categories' => ['Comics & Graphic Novels / Manga'],
                'imageLinks' => [
                    'thumbnail' => str_replace('https://', 'http://', $coverUrl) . '&edge=curl',
                ],
                'language' => 'fr',
            ],
        ];
    }

    public function testFindByIsbnReturnsDtoWhenCoverFound(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['items' => [$this->volumeItemWithCover()]])),
        ]);

        $isbn = $this->makeIsbn();
        $result = $this->makeClient($httpClient)->findByIsbn($isbn);

        $this->assertInstanceOf(MangaVolumeCoverDto::class, $result);
        $this->assertSame('google_books', $result->source);
        $this->assertNotNull($result->coverUrl);
        $this->assertStringStartsWith('https://', $result->coverUrl);
        $this->assertStringNotContainsString('&edge=curl', $result->coverUrl);
    }

    public function testFindByIsbnReturnsNullWhenNoItems(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['totalItems' => 0])),
        ]);

        $result = $this->makeClient($httpClient)->findByIsbn($this->makeIsbn());

        $this->assertNull($result);
    }

    public function testFindByIsbnReturnsNullWhenHttpError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Service Unavailable', ['http_code' => 503]),
        ]);

        $result = $this->makeClient($httpClient)->findByIsbn($this->makeIsbn());

        $this->assertNull($result);
    }

    public function testFindByIsbnReturnsNullWhenItemHasNoCover(): void
    {
        $item = [
            'id' => 'google-vol-2',
            'volumeInfo' => [
                'title' => 'No Cover Book',
                'categories' => ['Comics & Graphic Novels / Manga'],
                'language' => 'fr',
            ],
        ];

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['items' => [$item]])),
        ]);

        $result = $this->makeClient($httpClient)->findByIsbn($this->makeIsbn());

        $this->assertNull($result);
    }

    public function testFindByContextReturnsDtoWhenCoverFound(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['items' => [$this->volumeItemWithCover()]])),
        ]);

        $result = $this->makeClient($httpClient)->findByContext('One Piece', null, 1);

        $this->assertInstanceOf(MangaVolumeCoverDto::class, $result);
        $this->assertSame('google_books', $result->source);
        $this->assertNull($result->spineUrl);
    }

    public function testFindByContextReturnsNullWhenNoResults(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['totalItems' => 0])),
        ]);

        $result = $this->makeClient($httpClient)->findByContext('Unknown Manga', null, 42);

        $this->assertNull($result);
    }

    public function testCountryOmittedByDefault(): void
    {
        $requestedUrls = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrls): MockResponse {
            $requestedUrls[] = $url;
            return new MockResponse(json_encode(['totalItems' => 0]));
        });

        $this->makeClient($httpClient)->findByIsbn($this->makeIsbn());

        $this->assertCount(1, $requestedUrls);
        $this->assertStringNotContainsString('country=', $requestedUrls[0]);
    }

    public function testCountrySentOnlyWhenConfigured(): void
    {
        $requestedUrls = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrls): MockResponse {
            $requestedUrls[] = $url;
            return new MockResponse(json_encode(['totalItems' => 0]));
        });

        $client = new GoogleBooksMangaApiClient($httpClient, self::API_KEY, new NullLogger(), 'FR');
        $client->findByIsbn($this->makeIsbn());

        $this->assertCount(1, $requestedUrls);
        $this->assertStringContainsString('country=FR', $requestedUrls[0]);
    }

    public function testKeyIsOmittedFromQueryWhenNotConfigured(): void
    {
        $requestedUrls = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrls): MockResponse {
            $requestedUrls[] = $url;
            return new MockResponse(json_encode(['totalItems' => 0]));
        });

        $keylessClient = new GoogleBooksMangaApiClient($httpClient, '', new NullLogger());
        $keylessClient->findByIsbn($this->makeIsbn());

        $this->assertCount(1, $requestedUrls);
        $this->assertStringNotContainsString('key=', $requestedUrls[0]);
    }

    public function testSearchByTitleSendsRawQueryWithoutForcingMangaKeyword(): void
    {
        $requestedUrls = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrls): MockResponse {
            $requestedUrls[] = $url;
            return new MockResponse(json_encode(['items' => []]));
        });

        $this->makeClient($httpClient)->searchByTitle('naruto');

        $this->assertCount(1, $requestedUrls);
        // The "+manga" keyword that used to be forced onto every query is gone:
        // the raw term is sent verbatim so the caller controls any keyword.
        $this->assertStringContainsString('q=naruto', $requestedUrls[0]);
        $this->assertStringNotContainsString('manga', $requestedUrls[0]);
        $this->assertStringContainsString('langRestrict=fr', $requestedUrls[0]);
    }

    public function testSearchByTitleMapsFrenchMangaResult(): void
    {
        $payload = [
            'items' => [
                [
                    'id' => 'gbooks-1',
                    'volumeInfo' => [
                        'title' => 'Naruto',
                        'authors' => ['Masashi Kishimoto'],
                        'publisher' => 'Kana',
                        'language' => 'fr',
                        'description' => 'A ninja story.',
                        'categories' => ['Comics & Graphic Novels / Manga'],
                        'imageLinks' => ['thumbnail' => 'http://books.google.com/naruto.jpg'],
                    ],
                ],
            ],
        ];

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode($payload)),
        ]);

        $results = $this->makeClient($httpClient)->searchByTitle('naruto');

        $this->assertCount(1, $results);
        $this->assertSame('gbooks-1', $results[0]->externalId);
        $this->assertSame('Naruto', $results[0]->title);
        $this->assertSame('google', $results[0]->source);
        $this->assertStringStartsWith('https://', (string) $results[0]->coverUrl);
    }
}
