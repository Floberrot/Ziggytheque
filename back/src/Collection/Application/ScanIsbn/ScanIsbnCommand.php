<?php

declare(strict_types=1);

namespace App\Collection\Application\ScanIsbn;

final readonly class ScanIsbnCommand
{
    public function __construct(public string $isbn)
    {
    }
}
