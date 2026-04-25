<?php

declare(strict_types=1);

namespace App\Collection\Shared\Event;

use Symfony\Component\Uid\Uuid;
use App\Shared\Domain\Event\StartedEventInterface;

final readonly class ToggleVolumeStartedEvent implements StartedEventInterface
{
    public string $correlationId;

    public function __construct(
        public string $collectionEntryId,
        public string $volumeEntryId,
        public string $field,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
