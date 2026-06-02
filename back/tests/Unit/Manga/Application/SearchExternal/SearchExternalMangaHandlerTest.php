<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\SearchExternal;

use App\Manga\Application\SearchExternal\SearchExternalMangaHandler;
use App\Manga\Application\SearchExternal\SearchExternalMangaQuery;
use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class SearchExternalMangaHandlerTest extends TestCase
{
    /** A client that always returns one DTO whose title identifies the client. */
    private function clientReturning(string $marker): ExternalApiClientInterface
    {
        return new class ($marker) implements ExternalApiClientInterface {
            public function __construct(private string $marker)
            {
            }

            /** @return ExternalMangaDto[] */
            public function searchByTitle(string $query, string $type = 'manga', int $page = 1): array
            {
                return [new ExternalMangaDto(
                    externalId: $this->marker,
                    title: $this->marker,
                    edition: null,
                    author: null,
                    summary: null,
                    coverUrl: null,
                    genre: null,
                    language: 'fr',
                    source: $this->marker,
                    totalVolumes: null,
                )];
            }

            public function getMangaById(string $externalId): ?ExternalMangaDto
            {
                return null;
            }
        };
    }

    /** @param array<string, ExternalApiClientInterface> $services */
    private function locator(array $services): ContainerInterface
    {
        return new class ($services) implements ContainerInterface {
            /** @param array<string, ExternalApiClientInterface> $services */
            public function __construct(private array $services)
            {
            }

            public function get(string $id): mixed
            {
                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }

    public function testUsesDefaultClientWhenNoProviderRequested(): void
    {
        $handler = new SearchExternalMangaHandler(
            $this->clientReturning('default'),
            $this->locator(['googlebooks' => $this->clientReturning('googlebooks')]),
        );

        $results = $handler(new SearchExternalMangaQuery('one piece'));

        $this->assertCount(1, $results);
        $this->assertSame('default', $results[0]['title']);
    }

    public function testUsesRequestedProviderWhenKnown(): void
    {
        $handler = new SearchExternalMangaHandler(
            $this->clientReturning('default'),
            $this->locator(['googlebooks' => $this->clientReturning('googlebooks')]),
        );

        $results = $handler(new SearchExternalMangaQuery('one piece', provider: 'googlebooks'));

        $this->assertCount(1, $results);
        $this->assertSame('googlebooks', $results[0]['title']);
    }

    public function testFallsBackToDefaultClientForUnknownProvider(): void
    {
        $handler = new SearchExternalMangaHandler(
            $this->clientReturning('default'),
            $this->locator(['googlebooks' => $this->clientReturning('googlebooks')]),
        );

        $results = $handler(new SearchExternalMangaQuery('one piece', provider: 'does-not-exist'));

        $this->assertCount(1, $results);
        $this->assertSame('default', $results[0]['title']);
    }

    public function testMapsDtoFieldsToArrayShape(): void
    {
        $client = new class implements ExternalApiClientInterface {
            /** @return ExternalMangaDto[] */
            public function searchByTitle(string $query, string $type = 'manga', int $page = 1): array
            {
                return [new ExternalMangaDto(
                    externalId: 'ext-1',
                    title: 'Berserk',
                    edition: 'Glénat',
                    author: 'Kentaro Miura',
                    summary: 'A dark fantasy.',
                    coverUrl: 'https://example.test/cover.jpg',
                    genre: 'seinen',
                    language: 'fr',
                    source: 'jikan',
                    totalVolumes: 42,
                )];
            }

            public function getMangaById(string $externalId): ?ExternalMangaDto
            {
                return null;
            }
        };

        $handler = new SearchExternalMangaHandler($client, $this->locator([]));

        $results = $handler(new SearchExternalMangaQuery('berserk'));

        $this->assertSame([
            'externalId' => 'ext-1',
            'title' => 'Berserk',
            'edition' => 'Glénat',
            'author' => 'Kentaro Miura',
            'summary' => 'A dark fantasy.',
            'coverUrl' => 'https://example.test/cover.jpg',
            'genre' => 'seinen',
            'language' => 'fr',
            'totalVolumes' => 42,
        ], $results[0]);
    }
}
