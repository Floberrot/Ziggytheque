<?php

declare(strict_types=1);

namespace App\Notification\Shared\Event;

final readonly class RssFetchFailedEvent
{
    public function __construct(
        public string $correlationId,
        public string $feedName,
        public string $collectionEntryId,
        public string $error,
        public string $exceptionClass,
    ) {
    }
}
