<?php

declare(strict_types=1);

namespace App\Shared\Application\Pagination;

/**
 * Page and page size of a listing, kept in range whatever the client sends:
 * `page=0` or `limit=100000` never reach the database.
 */
abstract readonly class AbstractPaginatedQuery
{
    public const int MAX_LIMIT = 100;

    public int $page;
    public int $limit;

    public function __construct(int $page = 1, int $limit = 20)
    {
        $this->page  = max(1, $page);
        $this->limit = min(self::MAX_LIMIT, max(1, $limit));
    }
}
