<?php

declare(strict_types=1);

namespace App\Shared\Application\Pagination;

abstract readonly class AbstractPaginatedQuery
{
    public function __construct(
        public int $page = 1,
        public int $limit = 20,
    ) {
    }
}
