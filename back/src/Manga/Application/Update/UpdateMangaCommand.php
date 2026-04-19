<?php

declare(strict_types=1);

namespace App\Manga\Application\Update;

final readonly class UpdateMangaCommand
{
    public function __construct(
        public string $mangaId,
        public ?string $title = null,
        public ?string $edition = null,
        public ?string $coverUrl = null,
    ) {
    }
}
