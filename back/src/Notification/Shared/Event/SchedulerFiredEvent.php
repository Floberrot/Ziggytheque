<?php

declare(strict_types=1);

namespace App\Notification\Shared\Event;

use Symfony\Component\Uid\Uuid;

final readonly class SchedulerFiredEvent
{
    public string $correlationId;

    public function __construct(
        public int $followedCount,
        public int $jobsDispatched,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
