<?php

declare(strict_types=1);

namespace App\Shared\Application\Pagination;

/** @template T of object */
abstract class PaginatedResult
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        protected readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $limit,
    ) {
    }

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, limit: int} */
    public function toArray(): array
    {
        return [
            'items' => $this->serializeItems(),
            'total' => $this->total,
            'page'  => $this->page,
            'limit' => $this->limit,
        ];
    }

    /** @return list<array<string, mixed>> */
    abstract protected function serializeItems(): array;
}
