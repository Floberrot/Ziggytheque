<?php

declare(strict_types=1);

namespace App\Manga\Application\SearchExternal;

final readonly class SearchExternalMangaQuery
{
    public function __construct(
        public string $query,
        public string $type = 'manga',
        public int $page = 1,
    ) {
    }
}
