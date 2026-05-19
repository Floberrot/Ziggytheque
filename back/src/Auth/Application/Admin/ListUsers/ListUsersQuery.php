<?php

declare(strict_types=1);

namespace App\Auth\Application\Admin\ListUsers;

use App\Auth\Domain\UserStatusEnum;
use App\Shared\Application\Pagination\AbstractPaginatedQuery;

final readonly class ListUsersQuery extends AbstractPaginatedQuery
{
    public function __construct(
        public string $search = '',
        public ?UserStatusEnum $status = null,
        int $page = 1,
        int $limit = 20,
    ) {
        parent::__construct($page, $limit);
    }
}
