<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OpenLibraryMangaApiClient implements ExternalApiClientInterface
{
    private const BASE_URL = 'https://openlibrary.org/api';
    private const COVERS_URL = 'https://covers.openlibrary.org/b/id';

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
        $this->logger->info('OpenLibrary: searching', ['query' => $query, 'type' => $type, 'page' => $page]);

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/search.json', [
                'query' => [
                    'q' => $query . ' ' . $type,
                    'limit' => 20,
                    'offset' => ($page - 1) * 20,
                ],
            ]);

            $data = $response->toArray();
            $this->logger->info('OpenLibrary: received response', ['docs_count' => count($data['docs'] ?? [])]);

            if (empty($data['docs'])) {
                $this->logger->info('OpenLibrary: no results found');
                return [];
            }

            $results = array_values(array_filter(array_map(
                fn (array $item) => $this->mapToDto($item),
                $data['docs'],
            )));

            $this->logger->info('OpenLibrary: returning results', ['count' => count($results)]);

            return $results;
        } catch (\Throwable $e) {
            $this->logger->error('OpenLibrary: search failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getMangaById(string $externalId): ?ExternalMangaDto
    {
        $this->logger->info('OpenLibrary: fetching by id', ['externalId' => $externalId]);

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/books/' . $externalId . '.json');
            $data = $response->toArray();

            $result = $this->mapToDto($data);
            $this->logger->info('OpenLibrary: fetch complete', ['found' => $result !== null]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('OpenLibrary: fetch failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /** @param array<string, mixed> $item */
    private function mapToDto(array $item): ?ExternalMangaDto
    {
        $title = $item['title'] ?? null;
        if ($title === null) {
            return null;
        }

        $coverUrl = null;
        if (!empty($item['cover_id'])) {
            $coverUrl = sprintf('%s/%d-M.jpg', self::COVERS_URL, $item['cover_id']);
        }

        $author = null;
        if (!empty($item['author_name'])) {
            $author = is_array($item['author_name'])
                ? implode(', ', $item['author_name'])
                : $item['author_name'];
        }

        $edition = $item['publisher'][0] ?? null;
        $language = $item['language'][0] ?? 'fr';
        $summary = $item['description'] ?? null;
        $genre = $this->extractGenre($item['subject'] ?? []);

        return new ExternalMangaDto(
            externalId: $item['key'] ?? $item['edition_key'] ?? uniqid(),
            title: $title,
            edition: $edition,
            author: $author,
            summary: $summary,
            coverUrl: $coverUrl,
            genre: $genre,
            language: $language,
            source: 'openlibrary',
        );
    }

    /** @param string[] $subjects */
    private function extractGenre(array $subjects): ?string
    {
        if (empty($subjects)) {
            return null;
        }

        $raw = strtolower($subjects[0]);

        return match (true) {
            str_contains($raw, 'shonen') || str_contains($raw, 'shōnen') => 'shonen',
            str_contains($raw, 'shojo') || str_contains($raw, 'shōjo') => 'shojo',
            str_contains($raw, 'seinen') => 'seinen',
            str_contains($raw, 'josei') => 'josei',
            str_contains($raw, 'isekai') => 'isekai',
            str_contains($raw, 'fantasy') || str_contains($raw, 'fantaisie') => 'fantasy',
            str_contains($raw, 'action') => 'action',
            str_contains($raw, 'romance') => 'romance',
            str_contains($raw, 'horror') || str_contains($raw, 'horreur') => 'horror',
            str_contains($raw, 'science') || str_contains($raw, 'sci-fi') => 'sci_fi',
            str_contains($raw, 'sport') => 'sports',
            default => 'other',
        };
    }
}
