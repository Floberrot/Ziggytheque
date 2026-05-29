<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\EditionContext;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Infrastructure\ExternalApi\OpenLibraryCoversApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OpenLibraryCoversApiClientTest extends TestCase
{
    private const string BASE_URL = 'https://covers.openlibrary.org';

    private function makeClient(MockHttpClient $httpClient): OpenLibraryCoversApiClient
    {
        return new OpenLibraryCoversApiClient($httpClient, self::BASE_URL, new NullLogger());
    }

    private function makeIsbn(): Isbn
    {
        return Isbn::fromString('9782123456780');
    }

    public function testFindByIsbnReturnsDtoWhenCoverExists(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => ['content-length' => '50000'],
            ]),
        ]);

        $isbn = $this->makeIsbn();
        $result = $this->makeClient($httpClient)->findByIsbn($isbn);

        $this->assertInstanceOf(MangaVolumeCoverDto::class, $result);
        $this->assertSame('open_library', $result->source);
        $this->assertStringContainsString($isbn->value, $result->coverUrl);
        $this->assertSame($isbn, $result->isbn);
        $this->assertNull($result->spineUrl);
    }

    public function testFindByIsbnReturnsNullWhen404(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
        ]);

        $result = $this->makeClient($httpClient)->findByIsbn($this->makeIsbn());

        $this->assertNull($result);
    }

    public function testFindByIsbnReturnsNullWhenImageTooSmall(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => ['content-length' => '500'],  // < 2000 threshold
            ]),
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

    public function testFindByIsbnUsesCanonical13DigitIsbnInUrl(): void
    {
        $requestedUrls = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrls): MockResponse {
            $requestedUrls[] = $url;
            return new MockResponse('', ['http_code' => 404]);
        });

        $isbn = $this->makeIsbn();
        $this->makeClient($httpClient)->findByIsbn($isbn);

        $this->assertCount(1, $requestedUrls);
        $this->assertStringContainsString($isbn->value, $requestedUrls[0]);
        // Must use 13-digit canonical form, not hyphenated — strip URL-template suffix before checking
        $isbnSegment = str_replace('-L.jpg', '', parse_url($requestedUrls[0], PHP_URL_PATH) ?? '');
        $this->assertStringNotContainsString('-', $isbnSegment);
    }

    public function testFindByContextAlwaysReturnsNull(): void
    {
        $client = $this->makeClient(new MockHttpClient([]));

        $context = new EditionContext(mangaTitle: 'One Piece');
        $result = $client->findByContext($context, 1);

        $this->assertNull($result);
    }
}
