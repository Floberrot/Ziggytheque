<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use App\Manga\Infrastructure\Validator\ValidIsbn;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateVolumeRequest
{
    public function __construct(
        public ?string $coverUrl = null,
        public ?string $releaseDate = null,
        #[Assert\PositiveOrZero]
        public ?float $price = null,
        public ?string $spineUrl = null,
        #[ValidIsbn]
        public ?string $isbn = null,
    ) {
    }
}
