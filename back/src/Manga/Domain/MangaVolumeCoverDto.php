<?php

declare(strict_types=1);

namespace App\Manga\Domain;

final readonly class MangaVolumeCoverDto
{
    public function __construct(
        public string $coverUrl,
        public ?string $spineUrl,
        public ?Isbn $isbn,
        public string $source,
    ) {
    }
}
