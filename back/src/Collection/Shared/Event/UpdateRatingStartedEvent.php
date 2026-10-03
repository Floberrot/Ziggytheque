<?php

declare(strict_types=1);

namespace App\Collection\Shared\Event;

use App\Shared\Domain\Event\StartedEventInterface;
use Symfony\Component\Uid\Uuid;

/** A series of the collection is being rated (0 to 10). */
final readonly class UpdateRatingStartedEvent implements StartedEventInterface
{
    public string $correlationId;

    public function __construct(
        public string $collectionEntryId,
        public int $rating,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
