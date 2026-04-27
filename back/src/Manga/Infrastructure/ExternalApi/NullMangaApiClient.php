<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;

/** No-op stub used in tests to avoid real HTTP calls to external APIs. */
final readonly class NullMangaApiClient implements ExternalApiClientInterface
{
    /** @return ExternalMangaDto[] */
    public function searchByTitle(string $query, string $type = 'manga', int $page = 1): array
    {
        return [];
    }

    public function getMangaById(string $externalId): ?ExternalMangaDto
    {
        return null;
    }
}
