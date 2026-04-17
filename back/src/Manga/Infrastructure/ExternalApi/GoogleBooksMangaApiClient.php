<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GoogleBooksMangaApiClient implements ExternalApiClientInterface
{
    private const BASE_URL = 'https://www.googleapis.com/books/v1';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return ExternalMangaDto[]
     */
    public function searchByTitle(string $query, string $type = 'manga', int $page = 1): array
    {
        $this->logger->info('GoogleBooks: searching', ['query' => $query, 'type' => $type, 'page' => $page]);

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/volumes', [
                'query' => [
                    'q' => $query . '+manga',
                    'langRestrict' => 'fr',
                    'printType' => 'books',
                    'maxResults' => 20,
                    'startIndex' => ($page - 1) * 20,
                    'orderBy' => 'relevance',
                    'key' => $this->apiKey,
                ],
            ]);

            $data = $response->toArray();
            $this->logger->info('GoogleBooks: received response', ['items_count' => count($data['items'] ?? [])]);

            if (empty($data['items'])) {
                $this->logger->info('GoogleBooks: no results found');
                return [];
            }

            $results = array_values(array_filter(array_map(
                fn (array $item) => $this->mapToDto($item),
                $data['items'],
            )));

            $this->logger->info('GoogleBooks: returning results', ['count' => count($results)]);

            return $results;
        } catch (\Throwable $e) {
            $this->logger->error('GoogleBooks: search failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getMangaById(string $externalId): ?ExternalMangaDto
    {
        $this->logger->info('GoogleBooks: fetching by id', ['externalId' => $externalId]);

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/volumes/' . $externalId, [
                'query' => ['key' => $this->apiKey],
            ]);

            $data = $response->toArray();
            $result = $this->mapToDto($data);
            $this->logger->info('GoogleBooks: fetch complete', ['found' => $result !== null]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('GoogleBooks: fetch failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /** @param array<string, mixed> $item */
    private function mapToDto(array $item): ?ExternalMangaDto
    {
        $info = $item['volumeInfo'] ?? [];

        $title = $info['title'] ?? null;
        if ($title === null) {
            return null;
        }

        $coverUrl = $this->extractCoverUrl($info);
        $author = !empty($info['authors']) ? implode(', ', $info['authors']) : null;
        $edition = $info['publisher'] ?? null;
        $language = $info['language'] ?? 'fr';
        $summary = $info['description'] ?? null;
        $genre = $this->extractGenre($info['categories'] ?? []);
        $totalVolumes = $this->extractVolumeNumber($info);

        return new ExternalMangaDto(
            externalId: $item['id'],
            title: $title,
            edition: $edition,
            author: $author,
            summary: $summary,
            coverUrl: $coverUrl,
            genre: $genre,
            language: $language,
            totalVolumes: $totalVolumes,
            source: 'google',
        );
    }

    /** @param array<string, mixed> $info */
    private function extractCoverUrl(array $info): ?string
    {
        $url = $info['imageLinks']['thumbnail']
            ?? $info['imageLinks']['smallThumbnail']
            ?? null;

        if ($url === null) {
            return null;
        }

        // Google Books returns HTTP — force HTTPS
        return str_replace('http://', 'https://', $url);
    }

    /** @param string[] $categories */
    private function extractGenre(array $categories): ?string
    {
        if (empty($categories)) {
            return null;
        }

        $raw = strtolower($categories[0]);

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

    /** @param array<string, mixed> $info */
    private function extractVolumeNumber(array $info): ?int
    {
        // Try seriesInfo first
        if (!empty($info['seriesInfo']['bookDisplayNumber'])) {
            $num = filter_var($info['seriesInfo']['bookDisplayNumber'], FILTER_VALIDATE_INT);
            if ($num !== false) {
                return $num;
            }
        }

        return null;
    }
}
