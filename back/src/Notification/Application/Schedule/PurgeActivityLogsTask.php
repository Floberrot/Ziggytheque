<?php

declare(strict_types=1);

namespace App\Notification\Application\Schedule;

use App\Notification\Domain\Service\ActivityLogPurger;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Daily purge of activity logs older than the default retention window.
 * Runs at 04:30 UTC, before the 06:00 crawl dispatch, on the default schedule
 * provided by App\Schedule.
 */
#[AsCronTask('30 4 * * *')]
final readonly class PurgeActivityLogsTask
{
    public function __construct(private ActivityLogPurger $purger)
    {
    }

    public function __invoke(): void
    {
        $this->purger->purgeOlderThanDays(ActivityLogPurger::DEFAULT_RETENTION_DAYS);
    }
}
