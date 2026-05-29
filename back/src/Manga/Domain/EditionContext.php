<?php

declare(strict_types=1);

namespace App\Manga\Domain;

final readonly class EditionContext
{
    public function __construct(
        public string $mangaTitle,
        public ?string $publisher = null,
        public ?string $editionLabel = null,
        public ?int $year = null,
        public string $language = 'fr',
        public ?string $externalWorkId = null,
    ) {
    }
}
