<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ImportMangaRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $title,
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $edition,
        #[Assert\NotBlank]
        #[Assert\Length(max: 10)]
        public string $language,
        public ?string $author = null,
        public ?string $summary = null,
        public ?string $coverUrl = null,
        public ?string $genre = null,
        public ?string $externalId = null,
    ) {
    }
}
