<?php

declare(strict_types=1);

namespace App\Collection\Application\UpdateStatus;

final readonly class UpdateReadingStatusCommand
{
    public function __construct(
        public string $id,
        public string $status,
    ) {
    }
}
