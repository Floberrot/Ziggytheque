<?php

declare(strict_types=1);

namespace App\PriceCode\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdatePriceCodeRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $label,
        #[Assert\NotBlank]
        #[Assert\PositiveOrZero]
        public float $value,
    ) {
    }
}
