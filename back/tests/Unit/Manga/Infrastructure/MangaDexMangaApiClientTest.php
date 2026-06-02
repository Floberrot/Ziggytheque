<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

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
        return new MockResponse(json_encode([
            'data' => [
                ['id' => $mangaId, 'attributes' => ['title' => ['fr' => 'Test Manga']]],
            ],
        ]), ['response_headers' => ['Content-Type' => 'application/json']]);
    }

    private function coverListResponse(string $mangaId, int $volumeNumber, string $fileName): MockResponse
    {
        return new MockResponse(json_encode([
            'data' => [
                [
                    'id' => 'cover-id-1',
                    'attributes' => [
                        'volume' => (string) $volumeNumber,
                        'fileName' => $fileName,
                    ],
                ],
            ],
        ]), ['response_headers' => ['Content-Type' => 'application/json']]);
    }

    /** @param array<int, array{volume: string, fileName: string, locale: ?string}> $covers */
    private function coverListResponseRaw(array $covers): MockResponse
    {
        $data = [];
        foreach ($covers as $index => $cover) {
            $data[] = [
                'id' => 'cover-id-' . $index,
                'attributes' => [
                    'volume' => $cover['volume'],
                    'fileName' => $cover['fileName'],
                    'locale' => $cover['locale'],
                ],
            ];
        }

        return new MockResponse(
            json_encode(['data' => $data, 'total' => count($data)]),
            ['response_headers' => ['Content-Type' => 'application/json']],
        );
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

        $result = $this->makeClient($httpClient)->findByContext('Test Manga', null, 1);

        $this->assertInstanceOf(MangaVolumeCoverDto::class, $result);
        $this->assertSame('mangadex', $result->source);
        $this->assertStringContainsString(self::MANGA_ID, $result->coverUrl);
        $this->assertStringContainsString('cover.jpg', $result->coverUrl);
        $this->assertNull($result->isbn);
    }

    public function testFindByContextReturnsNullWhenNoMangaFound(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(
                json_encode(['data' => []]),
                ['response_headers' => ['Content-Type' => 'application/json']],
            ),
        ]);

        $result = $this->makeClient($httpClient)->findByContext('Unknown Manga', null, 1);

        $this->assertNull($result);
    }

    public function testFindByContextReturnsNullWhenVolumeNotInCovers(): void
    {
        $httpClient = new MockHttpClient([
            $this->mangaSearchResponse(self::MANGA_ID),
            // Cover list has volume 5, not the requested volume 1
            $this->coverListResponse(self::MANGA_ID, 5, 'cover5.jpg'),
        ]);

        $result = $this->makeClient($httpClient)->findByContext('Test Manga', null, 1);

        $this->assertNull($result);
    }

    public function testFindByContextReturnsNullOnHttpError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Internal Server Error', ['http_code' => 500]),
        ]);

        $result = $this->makeClient($httpClient)->findByContext('Test Manga', null, 1);

        $this->assertNull($result);
    }

    public function testReturnsJapaneseCoverWhenNoFrenchLocaleExists(): void
    {
        // Most MangaDex volume covers are the original Japanese art. Restricting to
        // the French locale (the old behaviour) returned nothing for these series.
        $httpClient = new MockHttpClient([
            $this->mangaSearchResponse(self::MANGA_ID),
            $this->coverListResponseRaw([
                ['volume' => '1', 'fileName' => 'japanese.jpg', 'locale' => 'ja'],
            ]),
        ]);

        $result = $this->makeClient($httpClient)->findByContext('Test Manga', null, 1);

        $this->assertInstanceOf(MangaVolumeCoverDto::class, $result);
        $this->assertStringContainsString('japanese.jpg', $result->coverUrl);
    }

    public function testPrefersRequestedLanguageOverJapanese(): void
    {
        $httpClient = new MockHttpClient([
            $this->mangaSearchResponse(self::MANGA_ID),
            $this->coverListResponseRaw([
                ['volume' => '1', 'fileName' => 'japanese.jpg', 'locale' => 'ja'],
                ['volume' => '1', 'fileName' => 'french.jpg', 'locale' => 'fr'],
            ]),
        ]);

        $result = $this->makeClient($httpClient)->findByContext('Test Manga', null, 1);

        $this->assertInstanceOf(MangaVolumeCoverDto::class, $result);
        $this->assertStringContainsString('french.jpg', $result->coverUrl);
    }

    public function testCleansTitleAndDropsRestrictiveFilters(): void
    {
        $capturedUrls = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$capturedUrls): MockResponse {
            $capturedUrls[] = $url;

            return str_contains($url, '/manga')
                ? $this->mangaSearchResponse(self::MANGA_ID)
                : $this->coverListResponseRaw([['volume' => '1', 'fileName' => 'cover.jpg', 'locale' => 'ja']]);
        });

        $result = $this->makeClient($httpClient)->findByContext('One Piece tome 1', 'édition originale', 1);

        $this->assertInstanceOf(MangaVolumeCoverDto::class, $result);

        $mangaUrl = urldecode($capturedUrls[0]);
        // Title is reduced to the bare series name…
        $this->assertStringContainsString('title=One Piece', $mangaUrl);
        $this->assertStringNotContainsStringIgnoringCase('tome', $mangaUrl);
        $this->assertStringNotContainsStringIgnoringCase('édition', $mangaUrl);
        // …and the restrictive scanlation-language filter is gone.
        $this->assertStringNotContainsString('availableTranslatedLanguage', $mangaUrl);

        // Covers must not be locale-restricted (the original Japanese art is wanted).
        $this->assertStringNotContainsString('locales', urldecode($capturedUrls[1]));
    }
}
