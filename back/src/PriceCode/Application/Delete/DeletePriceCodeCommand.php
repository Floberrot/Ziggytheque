<?php

declare(strict_types=1);

namespace App\PriceCode\Application\Delete;

final readonly class DeletePriceCodeCommand
{
    public function __construct(public string $code)
    {
    }
}
