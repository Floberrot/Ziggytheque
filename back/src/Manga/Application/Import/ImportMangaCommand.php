<?php

declare(strict_types=1);

namespace App\Manga\Application\Import;

final readonly class ImportMangaCommand
{
    public function __construct(
        public string $title,
        public string $edition,
        public string $language,
        public ?string $author = null,
        public ?string $summary = null,
        public ?string $coverUrl = null,
        public ?string $genre = null,
        public ?string $externalId = null,
        public ?int $totalVolumes = null,
    ) {
    }
}
