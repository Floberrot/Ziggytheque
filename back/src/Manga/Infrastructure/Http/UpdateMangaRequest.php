<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateMangaRequest
{
    public function __construct(
        #[Assert\Length(max: 255)]
        public ?string $title = null,
        #[Assert\Length(max: 100)]
        public ?string $edition = null,
        #[Assert\Length(max: 150)]
        public ?string $specialEdition = null,
        #[Assert\Url(protocols: ['http', 'https'], requireTld: true)]
        #[Assert\Length(max: 255)]
        public ?string $coverUrl = null,
    ) {
    }
}
