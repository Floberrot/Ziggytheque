<?php

declare(strict_types=1);

namespace App\PriceCode\Application\Create;

final readonly class CreatePriceCodeCommand
{
    public function __construct(
        public string $code,
        public string $label,
        public float $value,
    ) {
    }
}
