<?php

declare(strict_types=1);

namespace App\Notification\Shared\Event;

use App\Shared\Domain\Event\FailedEventInterface;

final readonly class JikanFetchFailedEvent implements FailedEventInterface
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
