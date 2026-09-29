<?php

declare(strict_types=1);

namespace App\Collection\Shared\Event;

use App\Shared\Domain\Event\StartedEventInterface;
use Symfony\Component\Uid\Uuid;

final readonly class AddFromCatalogueStartedEvent implements StartedEventInterface
{
    public string $correlationId;

    /** @param list<int> $volumeNumbers */
    public function __construct(
        public string $workTitle,
        public ?string $publisher,
        public ?string $specialEdition,
        public array $volumeNumbers,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
