<?php

declare(strict_types=1);

namespace App\Notification\Domain;

interface ActivityLogRepositoryInterface
{
    public function save(ActivityLog $log): void;

    /** @return ActivityLog[] */
    public function findRecent(int $limit = 50): array;
}
