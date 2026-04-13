<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddVolumeRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $number,
        public ?string $coverUrl = null,
        public ?string $priceCode = null,
        public ?string $releaseDate = null,
    ) {
    }
}
