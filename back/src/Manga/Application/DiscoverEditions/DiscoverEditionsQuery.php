<?php

declare(strict_types=1);

namespace App\Manga\Application\DiscoverEditions;

final readonly class DiscoverEditionsQuery
{
    public function __construct(
        public string $query,
        public ?string $author = null,
        public ?string $language = null,
    ) {
    }
}
