<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class JikanMangaApiClient implements ExternalApiClientInterface
{
    private const BASE_URL = 'https://api.jikan.moe/v4';

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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
            $this->logger->error('Jikan: fetch by id failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /** @param array<string, mixed> $item */
    private function mapToDto(array $item): ?ExternalMangaDto
    {
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
            totalVolumes: isset($item['volumes']) && $item['volumes'] !== null
                ? (int) $item['volumes']
                : null,
            source: 'jikan',
        );
    }

    /** @param array<string, mixed> $item */
    private function extractGenre(array $item): ?string
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
