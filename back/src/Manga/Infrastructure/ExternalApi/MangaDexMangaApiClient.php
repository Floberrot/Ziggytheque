<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MangaDexMangaApiClient implements ExternalApiClientInterface
{
    private const BASE_URL = 'https://api.mangadex.org';

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
        $this->logger->info('MangaDex: searching', ['query' => $query, 'type' => $type, 'page' => $page]);

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/manga', [
                'query' => [
                    'title' => $query,
                    'limit' => 20,
                    'offset' => ($page - 1) * 20,
                    'contentRating' => ['safe', 'suggestive', 'erotica', 'pornographic'],
                    'includes' => ['cover_art', 'author'],
                ],
            ]);

            $data = $response->toArray();
            $this->logger->info('MangaDex: received response', ['results_count' => count($data['data'] ?? [])]);

            if (empty($data['data'])) {
                $this->logger->info('MangaDex: no results found');
                return [];
            }

            $results = array_values(array_filter(array_map(
                fn (array $item) => $this->mapToDto($item),
                $data['data'],
            )));

            $this->logger->info('MangaDex: returning results', ['count' => count($results)]);

            return $results;
        } catch (\Throwable $e) {
            $this->logger->error('MangaDex: search failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getMangaById(string $externalId): ?ExternalMangaDto
    {
        $this->logger->info('MangaDex: fetching by id', ['externalId' => $externalId]);

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/manga/' . $externalId, [
                'query' => [
                    'includes' => ['cover_art', 'author'],
                ],
            ]);

            $data = $response->toArray();
            $result = $this->mapToDto($data['data']);
            $this->logger->info('MangaDex: fetch complete', ['found' => $result !== null]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('MangaDex: fetch failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /** @param array<string, mixed> $item */
    private function mapToDto(array $item): ?ExternalMangaDto
    {
        $title = $item['attributes']['title']['en'] ?? reset($item['attributes']['title']) ?? null;
        if ($title === null) {
            return null;
        }

        $author = null;
        foreach ($item['relationships'] as $rel) {
            if ($rel['type'] === 'author') {
                $author = $rel['attributes']['name'] ?? null;
                break;
            }
        }

        $coverUrl = null;
        foreach ($item['relationships'] as $rel) {
            if ($rel['type'] === 'cover_art') {
                $filename = $rel['attributes']['fileName'] ?? null;
                if ($filename) {
                    $coverUrl = sprintf('https://uploads.mangadex.org/covers/%s/%s.256.jpg', $item['id'], $filename);
                }
                break;
            }
        }

        $description = $item['attributes']['description']['en'] ?? reset($item['attributes']['description'] ?? []) ?? null;
        $genre = $this->extractGenre($item['attributes']['tags'] ?? []);

        return new ExternalMangaDto(
            externalId: $item['id'],
            title: $title,
            edition: null,
            author: $author,
            summary: $description,
            coverUrl: $coverUrl,
            genre: $genre,
            language: 'en',
            source: 'mangadex',
        );
    }

    /** @param array<int, array<string, mixed>> $tags */
    private function extractGenre(array $tags): ?string
    {
        if (empty($tags)) {
            return null;
        }

        foreach ($tags as $tag) {
            $name = strtolower($tag['attributes']['name']['en'] ?? '');

            return match (true) {
                str_contains($name, 'shonen') => 'shonen',
                str_contains($name, 'shojo') => 'shojo',
                str_contains($name, 'seinen') => 'seinen',
                str_contains($name, 'josei') => 'josei',
                str_contains($name, 'isekai') => 'isekai',
                str_contains($name, 'fantasy') => 'fantasy',
                str_contains($name, 'action') => 'action',
                str_contains($name, 'romance') => 'romance',
                str_contains($name, 'horror') => 'horror',
                str_contains($name, 'science fiction') || str_contains($name, 'sci-fi') => 'sci_fi',
                str_contains($name, 'sports') => 'sports',
                default => null,
            };
        }

        return null;
    }
}
