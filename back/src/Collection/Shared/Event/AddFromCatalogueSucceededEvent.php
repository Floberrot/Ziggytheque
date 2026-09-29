<?php

declare(strict_types=1);

namespace App\Collection\Shared\Event;

use App\Shared\Domain\Event\SucceededEventInterface;

final readonly class AddFromCatalogueSucceededEvent implements SucceededEventInterface
{
    /** @param list<int> $addedNumbers */
    public function __construct(
        public string $correlationId,
        public string $collectionEntryId,
        public string $mangaId,
        public string $workTitle,
        public bool $seriesCreated,
        public array $addedNumbers,
    ) {
    }
}
