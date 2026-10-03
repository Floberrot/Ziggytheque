<?php

declare(strict_types=1);

namespace App\Collection\Shared\Event;

use App\Shared\Domain\Event\StartedEventInterface;
use Symfony\Component\Uid\Uuid;

/** The reading status of a series of the collection is being changed. */
final readonly class UpdateReadingStatusStartedEvent implements StartedEventInterface
{
    public string $correlationId;

    public function __construct(
        public string $collectionEntryId,
        public string $status,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
