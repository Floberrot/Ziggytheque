<?php

declare(strict_types=1);

namespace App\Collection\Application\UpdateRating;

final readonly class UpdateRatingCommand
{
    public function __construct(
        public string $id,
        public int $rating,
    ) {
    }
}
