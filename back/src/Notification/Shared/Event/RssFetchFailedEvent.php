<?php

declare(strict_types=1);

namespace App\Notification\Shared\Event;

use App\Shared\Domain\Event\FailedEventInterface;

final readonly class RssFetchFailedEvent implements FailedEventInterface
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
