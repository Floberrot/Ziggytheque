<?php

declare(strict_types=1);

namespace App\Auth\Domain\ValueObject;

final readonly class BackfillReport
{
    public function __construct(
        public int $collectionEntries,
        public int $notifications,
        public int $articles,
        public int $activityLogs,
    ) {
    }

    public function total(): int
    {
        return $this->collectionEntries + $this->notifications + $this->articles + $this->activityLogs;
    }
}
