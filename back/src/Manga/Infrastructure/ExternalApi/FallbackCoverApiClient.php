<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Exception\ExternalApiUnavailableException;
use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use Psr\Log\LoggerInterface;

final readonly class FallbackCoverApiClient
{
    public function __construct(
        private ExternalApiClientInterface $primary,
        private ExternalApiClientInterface $secondary,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{source: string, results: ExternalMangaDto[]}
     */
    public function search(string $query, int $page = 1): array
    {
        $this->logger->info('FallbackCoverApiClient: starting search', ['query' => $query, 'page' => $page]);

        try {
            $this->logger->info('FallbackCoverApiClient: trying primary (OpenLibrary)');
            $results = $this->primary->searchByTitle($query, 'manga', $page);
            if ($results !== []) {
                $this->logger->info('FallbackCoverApiClient: primary succeeded', ['count' => count($results)]);
                return ['source' => 'openlibrary', 'results' => $results];
            }
            $this->logger->info('FallbackCoverApiClient: primary returned empty results');
        } catch (\Throwable $e) {
            $this->logger->warning('FallbackCoverApiClient: primary failed', ['error' => $e->getMessage()]);
        }

        try {
            $this->logger->info('FallbackCoverApiClient: trying secondary (GoogleBooks)');
            $results = $this->secondary->searchByTitle($query, 'manga', $page);
            $this->logger->info('FallbackCoverApiClient: secondary succeeded', ['count' => count($results)]);
            return ['source' => 'google', 'results' => $results];
        } catch (\Throwable $e) {
            $this->logger->error('FallbackCoverApiClient: both providers failed', ['error' => $e->getMessage()]);
            throw new ExternalApiUnavailableException();
        }
    }
}
