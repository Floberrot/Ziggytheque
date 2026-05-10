<?php

declare(strict_types=1);

namespace App\Manga\Application\SearchVolumeExternal;

final readonly class SearchVolumeExternalQuery
{
    public function __construct(
        public string $search,
        public int $page = 1,
        public ?int $volumeNumber = null,
        public ?string $edition = null,
        public string $provider = 'composite',
    ) {
    }
}
