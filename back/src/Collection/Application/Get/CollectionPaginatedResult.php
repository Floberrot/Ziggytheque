<?php

declare(strict_types=1);

namespace App\Collection\Application\Get;

use App\Collection\Domain\CollectionEntry;
use App\Shared\Application\Pagination\PaginatedResult;

/**
 * @extends PaginatedResult<CollectionEntry>
 */
final class CollectionPaginatedResult extends PaginatedResult
{
    protected function serializeItems(): array
    {
        return array_map(
            static fn (CollectionEntry $entry) => $entry->toArray(),
            $this->items,
        );
    }
}
