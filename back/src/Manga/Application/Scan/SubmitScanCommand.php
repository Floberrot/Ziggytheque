<?php

declare(strict_types=1);

namespace App\Manga\Application\Scan;

final readonly class SubmitScanCommand
{
    public function __construct(
        public string $scanToken,
        public string $isbn,
    ) {
    }
}
