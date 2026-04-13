<?php

declare(strict_types=1);

namespace App\Manga\Application\Get;

final readonly class GetMangaQuery
{
    public function __construct(public string $id)
    {
    }
}
