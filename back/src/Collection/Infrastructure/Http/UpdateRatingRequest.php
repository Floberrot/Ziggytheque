<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateRatingRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Type('integer')]
        #[Assert\Range(min: 0, max: 10)]
        public int $rating,
    ) {
    }
}
