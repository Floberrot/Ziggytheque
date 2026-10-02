<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use App\Manga\Domain\Manga;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddVolumeRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(Manga::MAX_VOLUMES)]
        public int $number,
        #[Assert\Url(protocols: ['http', 'https'], requireTld: true)]
        #[Assert\Length(max: 255)]
        public ?string $coverUrl = null,
        #[Assert\Date]
        public ?string $releaseDate = null,
    ) {
    }
}
