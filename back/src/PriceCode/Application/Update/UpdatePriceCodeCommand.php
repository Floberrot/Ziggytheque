<?php

declare(strict_types=1);

namespace App\PriceCode\Application\Update;

final readonly class UpdatePriceCodeCommand
{
    public function __construct(
        public string $code,
        public string $label,
        public float $value,
    ) {
    }
}
