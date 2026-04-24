<?php

declare(strict_types=1);

namespace App\Notification\Application\GetActivityLogs;

final readonly class GetActivityLogsQuery
{
    public function __construct(
        public int $page = 1,
        public int $limit = 50,
        public ?string $eventType = null,
        public ?string $status = null,
        public ?string $collectionEntryId = null,
    ) {
    }
}
