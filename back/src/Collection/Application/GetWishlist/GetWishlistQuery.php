<?php

declare(strict_types=1);

namespace App\Collection\Application\GetWishlist;

use App\Shared\Application\Pagination\AbstractPaginatedQuery;

final readonly class GetWishlistQuery extends AbstractPaginatedQuery
{
    public function __construct(
        public ?string $search = null,
        int $page = 1,
        int $limit = 20,
    ) {
        parent::__construct($page, $limit);
    }
}
