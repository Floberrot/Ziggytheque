<?php

declare(strict_types=1);

namespace App\Manga\Application\Enrich;

/** Asks the worker to fill a new series' genre / summary / author from MyAnimeList. */
final readonly class EnrichMangaMessage
{
    public function __construct(public string $mangaId)
    {
    }
}
