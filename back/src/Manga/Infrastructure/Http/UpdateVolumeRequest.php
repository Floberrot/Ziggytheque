<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateVolumeRequest
{
    public function __construct(
        public ?string $coverUrl = null,
        public ?string $releaseDate = null,
        #[Assert\PositiveOrZero]
        public ?float $price = null,
    ) {
    }
}
