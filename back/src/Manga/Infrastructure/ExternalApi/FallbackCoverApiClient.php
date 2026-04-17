<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Exception\ExternalApiUnavailableException;
use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;

final readonly class FallbackCoverApiClient
{
    public function __construct(
        private ExternalApiClientInterface $primary,
        private ExternalApiClientInterface $secondary,
    ) {
    }

    /**
     * @return array{source: string, results: ExternalMangaDto[]}
     */
    public function search(string $query, int $page = 1): array
    {
        try {
            $results = $this->primary->searchByTitle($query, 'manga', $page);
            if ($results !== []) {
                return ['source' => 'amazon', 'results' => $results];
            }
        } catch (\Throwable) {
        }

        try {
            $results = $this->secondary->searchByTitle($query, 'manga', $page);
            return ['source' => 'google', 'results' => $results];
        } catch (\Throwable) {
            throw new ExternalApiUnavailableException();
        }
    }
}
