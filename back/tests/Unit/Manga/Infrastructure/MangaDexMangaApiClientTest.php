<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\EditionContext;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Infrastructure\ExternalApi\MangaDexMangaApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MangaDexMangaApiClientTest extends TestCase
{
    private const string BASE_URL = 'https://api.mangadex.org';
    private const string MANGA_ID = 'abc-manga-id';

    private function makeClient(MockHttpClient $httpClient): MangaDexMangaApiClient
    {
        return new MangaDexMangaApiClient($httpClient, self::BASE_URL, new NullLogger());
    }

    private function mangaSearchResponse(string $mangaId): MockResponse
    {
        return new MockResponse((string) json_encode([
            'data' => [
                ['id' => $mangaId, 'attributes' => ['title' => ['fr' => 'Test Manga']]],
            ],
        ]), ['response_headers' => ['Content-Type' => 'application/json']]);
    }

    private function coverListResponse(string $mangaId, int $volumeNumber, string $fileName): MockResponse
    {
        return new MockResponse((string) json_encode([
            'data' => [
                [
                    'id'         => 'cover-id-1',
                    'attributes' => [
                        'volume'   => (string) $volumeNumber,
                        'fileName' => $fileName,
                    ],
                ],
            ],
        ]), ['response_headers' => ['Content-Type' => 'application/json']]);
    }

    public function testFindByIsbnAlwaysReturnsNull(): void
    {
        $client = $this->makeClient(new MockHttpClient([]));
        $isbn = Isbn::fromString('9782123456780');

        $this->assertNull($client->findByIsbn($isbn));
    }

    public function testFindByContextReturnsCoverWhenVolumeFound(): void
    {
        $httpClient = new MockHttpClient([
            $this->mangaSearchResponse(self::MANGA_ID),
            $this->coverListResponse(self::MANGA_ID, 1, 'cover.jpg'),
        ]);

        $context = new EditionContext(mangaTitle: 'Test Manga');
        $result = $this->makeClient($httpClient)->findByContext($context, 1);

        $this->assertInstanceOf(MangaVolumeCoverDto::class, $result);
        $this->assertSame('mangadex', $result->source);
        $this->assertStringContainsString(self::MANGA_ID, $result->coverUrl);
        $this->assertStringContainsString('cover.jpg', $result->coverUrl);
        $this->assertNull($result->isbn);
    }

    public function testFindByContextReturnsNullWhenNoMangaFound(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode(['data' => []]), ['response_headers' => ['Content-Type' => 'application/json']]),
        ]);

        $context = new EditionContext(mangaTitle: 'Unknown Manga');
        $result = $this->makeClient($httpClient)->findByContext($context, 1);

        $this->assertNull($result);
    }

    public function testFindByContextReturnsNullWhenVolumeNotInCovers(): void
    {
        $httpClient = new MockHttpClient([
            $this->mangaSearchResponse(self::MANGA_ID),
            $this->coverListResponse(self::MANGA_ID, 5, 'cover5.jpg'),
        ]);

        $context = new EditionContext(mangaTitle: 'Test Manga');
        $result = $this->makeClient($httpClient)->findByContext($context, 1);

        $this->assertNull($result);
    }

    public function testFindByContextReturnsNullOnHttpError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Internal Server Error', ['http_code' => 500]),
        ]);

        $context = new EditionContext(mangaTitle: 'Test Manga');
        $result = $this->makeClient($httpClient)->findByContext($context, 1);

        $this->assertNull($result);
    }
}
