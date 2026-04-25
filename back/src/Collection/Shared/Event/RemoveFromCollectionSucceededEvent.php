<?php

declare(strict_types=1);

namespace App\Collection\Shared\Event;

use App\Shared\Domain\Event\SucceededEventInterface;

final readonly class RemoveFromCollectionSucceededEvent implements SucceededEventInterface
{
    public function __construct(
        public string $correlationId,
        public string $collectionEntryId,
    ) {
    }
}
