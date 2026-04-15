<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;

/**
 * Null implementation — returns empty results.
 * Replace with MangaDexApiClient, AniListApiClient, etc. when ready.
 */
final class NullMangaApiClient implements ExternalApiClientInterface
{
    public function searchByTitle(string $query, string $type = 'manga', int $page = 1): array
    {
        return [];
    }

    public function getMangaById(string $externalId): ?ExternalMangaDto
    {
        return null;
    }
}
