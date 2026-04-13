<?php

declare(strict_types=1);

namespace App\Manga\Application\Search;

final readonly class SearchMangaQuery
{
    public function __construct(public string $query)
    {
    }
}
