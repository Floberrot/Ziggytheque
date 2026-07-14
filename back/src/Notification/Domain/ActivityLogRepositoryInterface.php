<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use DateTimeImmutable;

interface ActivityLogRepositoryInterface
{
    public function save(ActivityLog $log): void;

    public function findById(string $id): ?ActivityLog;

    /**
     * @param array{
     *     eventType?: string,
     *     status?: string,
     *     collectionEntryId?: string,
     *     ownerId?: string,
     *     from?: DateTimeImmutable,
     *     to?: DateTimeImmutable,
     *     search?: string,
     * } $filters
     * @return array{items: list<ActivityLog>, total: int}
     */
    public function findPaginated(int $page, int $limit, array $filters = []): array;

    public function countRecentErrors(int $windowMinutes = 10): int;

    /** @return int Number of deleted rows */
    public function deleteOlderThan(DateTimeImmutable $before): int;
}
