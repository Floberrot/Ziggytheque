<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class BatchSetVolumePriceRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public float $price,
    ) {
    }
}
