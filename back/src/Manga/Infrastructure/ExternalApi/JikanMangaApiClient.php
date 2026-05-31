<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use Psr\Log\LoggerInterface;
use Throwable;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class JikanMangaApiClient implements ExternalApiClientInterface
{
    private const BASE_URL = 'https://api.jikan.moe/v4';

    /**
     * MyAnimeList genre IDs that flag adult content: Ecchi (9), Hentai (12),
     * Erotica (49). Jikan also groups these under the `explicit_genres` array.
     */
    private const ADULT_GENRE_IDS = [9, 12, 49];

    /** Case-insensitive genre/theme name fragments that flag adult content. */
    private const ADULT_GENRE_KEYWORDS = ['hentai', 'erotica', 'ecchi', 'adult'];

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return ExternalMangaDto[]
     */
    public function searchByTitle(string $query, string $type = 'manga', int $page = 1): array
    {
        $this->logger->info('Jikan: searching', ['query' => $query, 'type' => $type, 'page' => $page]);

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/manga', [
                'query' => [
                    'q' => $query,
                    'type' => $type,
                    'limit' => 20,
                    'page' => $page,
                    // Ask Jikan to drop adult (Rx / Hentai) entries server-side.
                    'sfw' => 'true',
                ],
            ]);

            $data = $response->toArray();
            $items = $data['data'] ?? [];

            $this->logger->info('Jikan: received response', ['count' => count($items)]);

            $results = array_values(array_filter(array_map(
                fn (array $item) => $this->mapToDto($item),
                $items,
            )));

            $this->logger->info('Jikan: returning results', ['count' => count($results)]);

            return $results;
        } catch (Throwable $e) {
            $this->logger->error('Jikan: search failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getMangaById(string $externalId): ?ExternalMangaDto
    {
        $this->logger->info('Jikan: fetching by id', ['externalId' => $externalId]);

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/manga/' . $externalId);
            $data = $response->toArray();
            $item = $data['data'] ?? null;

            if ($item === null) {
                return null;
            }

            $result = $this->mapToDto($item);
            $this->logger->info('Jikan: fetch complete', ['found' => $result !== null]);

            return $result;
        } catch (Throwable $e) {
            $this->logger->error('Jikan: fetch by id failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /** @param array<string, mixed> $item */
    private function mapToDto(array $item): ?ExternalMangaDto
    {
        // Defence in depth: drop NSFW / 18+ entries even when they slip past the
        // server-side `sfw` filter (e.g. Ecchi/Erotica) or come from the by-id
        // endpoint, which has no such parameter.
        if ($this->isAdultContent($item)) {
            $this->logger->info('Jikan: filtered out adult entry', ['mal_id' => $item['mal_id'] ?? null]);

            return null;
        }

        $title = $item['title'] ?? $item['title_english'] ?? null;
        if ($title === null) {
            return null;
        }

        $coverUrl = $item['images']['jpg']['large_image_url']
            ?? $item['images']['jpg']['image_url']
            ?? $item['images']['webp']['large_image_url']
            ?? $item['images']['webp']['image_url']
            ?? null;

        $authorNames = array_filter(array_map(
            static fn (array $a) => $a['name'] ?? null,
            $item['authors'] ?? [],
        ));
        $author = !empty($authorNames) ? implode(', ', $authorNames) : null;

        return new ExternalMangaDto(
            externalId: (string) $item['mal_id'],
            title: $title,
            edition: null,
            author: $author,
            summary: $item['synopsis'] ?? null,
            coverUrl: $coverUrl,
            genre: $this->extractGenre($item),
            language: 'fr',
            source: 'jikan',
            totalVolumes: isset($item['volumes'])
                ? (int) $item['volumes']
                : null,
        );
    }

    /**
     * Detects NSFW / 18+ entries. Jikan exposes adult taxonomy both via the
     * dedicated `explicit_genres` array and, occasionally, mixed into the
     * regular `genres`/`themes` lists — so we check all of them by id and name.
     *
     * @param array<string, mixed> $item
     */
    private function isAdultContent(array $item): bool
    {
        if (!empty($item['explicit_genres'])) {
            return true;
        }

        foreach (['genres', 'explicit_genres', 'themes'] as $taxonomy) {
            foreach ($item[$taxonomy] ?? [] as $entry) {
                if (in_array($entry['mal_id'] ?? null, self::ADULT_GENRE_IDS, true)) {
                    return true;
                }

                $name = strtolower((string) ($entry['name'] ?? ''));
                foreach (self::ADULT_GENRE_KEYWORDS as $keyword) {
                    if (str_contains($name, $keyword)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** @param array<string, mixed> $item */
    private function extractGenre(array $item): string
    {
        // Demographics take priority (Shonen, Shojo, Seinen, Josei)
        foreach ($item['demographics'] ?? [] as $demo) {
            $name = strtolower($demo['name'] ?? '');
            $mapped = match (true) {
                str_contains($name, 'shounen') || str_contains($name, 'shonen') => 'shonen',
                str_contains($name, 'shoujo') || str_contains($name, 'shojo') => 'shojo',
                str_contains($name, 'seinen') => 'seinen',
                str_contains($name, 'josei') => 'josei',
                default => null,
            };

            if ($mapped !== null) {
                return $mapped;
            }
        }

        // Themes (Isekai, etc.)
        foreach ($item['themes'] ?? [] as $theme) {
            $name = strtolower($theme['name'] ?? '');
            if (str_contains($name, 'isekai')) {
                return 'isekai';
            }
        }

        // Genres
        foreach ($item['genres'] ?? [] as $genre) {
            $name = strtolower($genre['name'] ?? '');
            $mapped = match (true) {
                str_contains($name, 'action') => 'action',
                str_contains($name, 'romance') => 'romance',
                str_contains($name, 'horror') => 'horror',
                str_contains($name, 'fantasy') => 'fantasy',
                str_contains($name, 'sci-fi') || str_contains($name, 'science fiction') => 'sci_fi',
                str_contains($name, 'sport') => 'sports',
                default => null,
            };
            if ($mapped !== null) {
                return $mapped;
            }
        }

        return 'other';
    }
}
