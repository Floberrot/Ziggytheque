<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use App\Manga\Infrastructure\Validator\ValidIsbn;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateVolumeRequest
{
    public function __construct(
        #[Assert\Url(protocols: ['http', 'https'], requireTld: true)]
        #[Assert\Length(max: 255)]
        public ?string $coverUrl = null,
        #[Assert\Date]
        public ?string $releaseDate = null,
        #[Assert\PositiveOrZero]
        public ?float $price = null,
        #[ValidIsbn]
        public ?string $isbn = null,
    ) {
    }
}
