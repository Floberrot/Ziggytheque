<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\ExternalMangaDto;
use App\Manga\Infrastructure\ExternalApi\JikanMangaApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class JikanMangaApiClientTest extends TestCase
{
    private function makeClient(MockHttpClient $httpClient): JikanMangaApiClient
    {
        return new JikanMangaApiClient($httpClient, new NullLogger());
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function safeItem(array $overrides = []): array
    {
        return array_merge([
            'mal_id'        => 1,
            'title'         => 'One Piece',
            'synopsis'      => 'Pirates.',
            'images'        => ['jpg' => ['large_image_url' => 'https://cdn.myanimelist.net/op.jpg']],
            'authors'       => [['name' => 'Oda, Eiichiro']],
            'demographics'  => [['mal_id' => 27, 'name' => 'Shounen']],
            'genres'        => [['mal_id' => 1, 'name' => 'Action']],
            'explicit_genres' => [],
            'themes'        => [],
            'volumes'       => 105,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function adultItem(array $overrides = []): array
    {
        return $this->safeItem(array_merge([
            'mal_id'          => 666,
            'title'           => 'Forbidden',
            'explicit_genres' => [['mal_id' => 12, 'name' => 'Hentai']],
        ], $overrides));
    }

    /** @param array<int, array<string, mixed>> $items */
    private function searchResponse(array $items): MockResponse
    {
        return new MockResponse((string) json_encode(['data' => $items]));
    }

    public function testSearchAsksJikanForSafeForWorkContent(): void
    {
        $capturedUrl = null;
        $httpClient  = new MockHttpClient(function (string $method, string $url) use (&$capturedUrl): MockResponse {
            $capturedUrl = $url;

            return new MockResponse((string) json_encode(['data' => []]));
        });

        $this->makeClient($httpClient)->searchByTitle('naruto');

        $this->assertNotNull($capturedUrl);
        $this->assertStringContainsString('sfw=true', $capturedUrl);
    }

    public function testSearchReturnsMappedSafeResults(): void
    {
        $httpClient = new MockHttpClient([
            $this->searchResponse([$this->safeItem()]),
        ]);

        $results = $this->makeClient($httpClient)->searchByTitle('one piece');

        $this->assertCount(1, $results);
        $this->assertInstanceOf(ExternalMangaDto::class, $results[0]);
        $this->assertSame('1', $results[0]->externalId);
        $this->assertSame('One Piece', $results[0]->title);
        $this->assertSame('shonen', $results[0]->genre);
        $this->assertSame('jikan', $results[0]->source);
    }

    public function testSearchFiltersOutEntriesWithExplicitGenres(): void
    {
        $httpClient = new MockHttpClient([
            $this->searchResponse([$this->safeItem(), $this->adultItem()]),
        ]);

        $results = $this->makeClient($httpClient)->searchByTitle('mixed');

        $this->assertCount(1, $results);
        $this->assertSame('One Piece', $results[0]->title);
    }

    public function testSearchFiltersOutAdultGenreListedUnderRegularGenres(): void
    {
        $sneaky = $this->safeItem([
            'mal_id'          => 7,
            'title'           => 'Sneaky',
            'explicit_genres' => [],
            'genres'          => [['mal_id' => 49, 'name' => 'Erotica']],
        ]);

        $httpClient = new MockHttpClient([
            $this->searchResponse([$sneaky]),
        ]);

        $results = $this->makeClient($httpClient)->searchByTitle('sneaky');

        $this->assertSame([], $results);
    }

    public function testSearchFiltersOutEcchiByNameEvenWithoutKnownId(): void
    {
        $ecchi = $this->safeItem([
            'mal_id' => 8,
            'title'  => 'Fan Service',
            'genres' => [['mal_id' => 9999, 'name' => 'Ecchi']],
        ]);

        $httpClient = new MockHttpClient([
            $this->searchResponse([$ecchi]),
        ]);

        $results = $this->makeClient($httpClient)->searchByTitle('fan');

        $this->assertSame([], $results);
    }

    public function testGetMangaByIdReturnsDtoForSafeContent(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode(['data' => $this->safeItem()])),
        ]);

        $result = $this->makeClient($httpClient)->getMangaById('1');

        $this->assertInstanceOf(ExternalMangaDto::class, $result);
        $this->assertSame('One Piece', $result->title);
    }

    public function testGetMangaByIdReturnsNullForAdultContent(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode(['data' => $this->adultItem()])),
        ]);

        $result = $this->makeClient($httpClient)->getMangaById('666');

        $this->assertNull($result);
    }
}
