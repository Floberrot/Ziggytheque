<?php

declare(strict_types=1);

namespace App\Manga\Domain;

/**
 * Prepared interface for external manga API integration.
 * Implement this interface to connect to MangaDex, AniList, or any other provider.
 */
interface ExternalApiClientInterface
{
    /**
     * Search mangas by title from the external API.
     *
     * @param string $type One of: manga, manhwa, manhua
     * @return ExternalMangaDto[]
     */
    public function searchByTitle(string $query, string $type = 'manga', int $page = 1): array;

    /**
     * Fetch full manga details including volumes from the external API.
     */
    public function getMangaById(string $externalId): ?ExternalMangaDto;
}
