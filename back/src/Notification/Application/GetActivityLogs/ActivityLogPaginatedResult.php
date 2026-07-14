<?php

declare(strict_types=1);

namespace App\Notification\Application\GetActivityLogs;

use App\Notification\Domain\ActivityLog;
use App\Shared\Application\Pagination\PaginatedResult;

/**
 * @extends PaginatedResult<ActivityLog>
 */
final class ActivityLogPaginatedResult extends PaginatedResult
{
    protected function serializeItems(): array
    {
        return array_map(
            static fn (ActivityLog $log) => $log->toArray(),
            $this->items,
        );
    }
}
