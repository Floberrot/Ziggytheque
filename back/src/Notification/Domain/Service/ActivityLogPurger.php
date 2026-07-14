<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

use App\Notification\Domain\ActivityLogRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Deletes activity logs older than the retention window.
 * Called by the daily scheduled task and the app:activity-log:purge console command.
 */
final readonly class ActivityLogPurger
{
    public const DEFAULT_RETENTION_DAYS = 90;

    public function __construct(private ActivityLogRepositoryInterface $activityLogRepository)
    {
    }

    /** @return int Number of deleted rows */
    public function purgeOlderThanDays(int $days = self::DEFAULT_RETENTION_DAYS): int
    {
        if ($days < 1) {
            throw new InvalidArgumentException('Retention must be at least 1 day.');
        }

        $cutoff = new DateTimeImmutable(sprintf('-%d days', $days));

        return $this->activityLogRepository->deleteOlderThan($cutoff);
    }
}
