<?php

declare(strict_types=1);

namespace App\Notification\Shared\Event;

final readonly class JikanFetchFailedEvent
{
    public function __construct(
        public string $correlationId,
        public string $malId,
        public string $collectionEntryId,
        public string $error,
        public string $exceptionClass,
    ) {
    }
}
