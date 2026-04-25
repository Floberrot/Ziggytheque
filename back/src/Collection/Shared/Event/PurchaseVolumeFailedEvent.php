<?php

declare(strict_types=1);

namespace App\Collection\Shared\Event;

use App\Shared\Domain\Event\FailedEventInterface;

final readonly class PurchaseVolumeFailedEvent implements FailedEventInterface
{
    public function __construct(
        public string $correlationId,
        public string $collectionEntryId,
        public string $volumeEntryId,
        public string $error,
        public string $exceptionClass,
    ) {
    }
}
