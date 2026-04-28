<?php

declare(strict_types=1);

namespace App\Collection\Application\UpdateStatus;

use App\Collection\Domain\ReadingStatusEnum;

final readonly class UpdateReadingStatusCommand
{
    public function __construct(
        public string $id,
        public ReadingStatusEnum $status,
    ) {
    }
}
