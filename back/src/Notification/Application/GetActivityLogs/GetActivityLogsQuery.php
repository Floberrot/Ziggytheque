<?php

declare(strict_types=1);

namespace App\Notification\Application\GetActivityLogs;

final readonly class GetActivityLogsQuery
{
    public function __construct(public int $limit = 50) {}
}
