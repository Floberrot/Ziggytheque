<?php

declare(strict_types=1);

namespace App\Notification\Domain;

interface ActivityLogRepositoryInterface
{
    public function save(ActivityLog $log): void;

    public function findById(string $id): ?ActivityLog;

    /**
     * @param array{eventType?: string, status?: string, collectionEntryId?: string} $filters
     * @return array{items: ActivityLog[], total: int}
     */
    public function findPaginated(int $page, int $limit, array $filters = []): array;

    public function countRecentErrors(int $windowMinutes = 10): int;
}
