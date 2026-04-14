<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Jikan v4 API client (MyAnimeList wrapper).
 * Free — no API key required.
 * Returns manga SERIES (oeuvre), not individual volumes.
 * Rate limit: 3 req/s, 60 req/min.
 */
final readonly class JikanMangaApiClient implements ExternalApiClientInterface
{
    private const BASE_URL = 'https://api.jikan.moe/v4';

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /** @return ExternalMangaDto[] */
    public function searchByTitle(string $query): array
    {
        $response = $this->httpClient->request('GET', self::BASE_URL . '/manga', [
            'query' => [
                'q'      => $query,
                'type'   => 'manga',
                'sfw'    => 'true',
                'limit'  => 20,
                'order_by' => 'popularity',
                'sort'   => 'asc',
            ],
        ]);

        $data = $response->toArray(throw: false);

        if (empty($data['data'])) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $item) => $this->mapToDto($item),
            $data['data'],
        )));
    }

    public function getMangaById(string $externalId): ?ExternalMangaDto
    {
        $response = $this->httpClient->request('GET', self::BASE_URL . '/manga/' . $externalId);

        $data = $response->toArray(throw: false);

        if (empty($data['data'])) {
            return null;
        }

        return $this->mapToDto($data['data']);
    }

    private function mapToDto(array $item): ?ExternalMangaDto
    {
        $malId = $item['mal_id'] ?? null;
        if ($malId === null) {
            return null;
        }

        // Prefer English title, fall back to default title
        $title = $item['title_english'] ?? $item['title'] ?? null;
        if ($title === null) {
            return null;
        }

        $coverUrl   = $item['images']['jpg']['large_image_url']
            ?? $item['images']['jpg']['image_url']
            ?? null;

        $totalVolumes = isset($item['volumes']) && $item['volumes'] > 0
            ? (int) $item['volumes']
            : null;

        $synopsis = $item['synopsis'] ?? null;
        // Strip MAL boilerplate "(Source: ...)" appended to synopses
        if ($synopsis !== null) {
            $synopsis = preg_replace('/\[Written by MAL Rewrite\]|\(Source:[^)]*\)/i', '', $synopsis);
            $synopsis = trim($synopsis);
        }

        $author = $this->extractAuthor($item['authors'] ?? []);
        $genre  = $this->extractGenre($item['genres'] ?? [], $item['themes'] ?? []);

        return new ExternalMangaDto(
            externalId: (string) $malId,
            title: $title,
            edition: null,          // User fills in the French publisher (Kana, Glénat…)
            author: $author,
            summary: $synopsis,
            coverUrl: $coverUrl,
            genre: $genre,
            language: 'fr',         // Default to French for this app
            totalVolumes: $totalVolumes,
        );
    }

    /** @param array<array{person: array{name: string}}> $authors */
    private function extractAuthor(array $authors): ?string
    {
        if (empty($authors)) {
            return null;
        }

        $names = array_map(
            static fn (array $a) => $a['person']['name'] ?? null,
            $authors,
        );

        $names = array_filter($names);

        return !empty($names) ? implode(', ', $names) : null;
    }

    private function extractGenre(array $genres, array $themes): ?string
    {
        $all = array_merge($genres, $themes);

        foreach ($all as $g) {
            $name = strtolower($g['name'] ?? '');
            $mapped = match (true) {
                str_contains($name, 'shonen') || str_contains($name, 'shounen') => 'shonen',
                str_contains($name, 'shojo')  || str_contains($name, 'shoujo')  => 'shojo',
                str_contains($name, 'seinen')  => 'seinen',
                str_contains($name, 'josei')   => 'josei',
                str_contains($name, 'isekai')  => 'isekai',
                str_contains($name, 'sports')  => 'sports',
                str_contains($name, 'horror')  => 'horror',
                str_contains($name, 'romance') => 'romance',
                str_contains($name, 'action')  => 'action',
                str_contains($name, 'fantasy') => 'fantasy',
                str_contains($name, 'sci-fi') || str_contains($name, 'science fiction') => 'sci_fi',
                str_contains($name, 'slice of life') => 'slice_of_life',
                default => null,
            };

            if ($mapped !== null) {
                return $mapped;
            }
        }

        return null;
    }
}
