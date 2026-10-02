<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use App\Manga\Domain\GenreEnum;
use App\Manga\Domain\Manga;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ImportMangaRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $title,
        #[Assert\NotBlank]
        #[Assert\Length(max: 10)]
        public string $language,
        #[Assert\Length(max: 100)]
        public ?string $edition = null,
        #[Assert\Length(max: 150)]
        public ?string $specialEdition = null,
        #[Assert\Length(max: 255)]
        public ?string $author = null,
        #[Assert\Length(max: 20000)]
        public ?string $summary = null,
        #[Assert\Url(protocols: ['http', 'https'], requireTld: true)]
        #[Assert\Length(max: 255)]
        public ?string $coverUrl = null,
        #[Assert\Choice(callback: [GenreEnum::class, 'values'])]
        public ?string $genre = null,
        #[Assert\Length(max: 100)]
        public ?string $externalId = null,
        #[Assert\Range(min: 0, max: Manga::MAX_VOLUMES)]
        public ?int $totalVolumes = null,
    ) {
    }
}
