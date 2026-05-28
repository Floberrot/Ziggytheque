<?php

declare(strict_types=1);

namespace App\Auth\Application\Admin\ListUsers;

use App\Auth\Domain\User;
use App\Shared\Application\Pagination\PaginatedResult;

/** @extends PaginatedResult<User> */
final class UserListResult extends PaginatedResult
{
    protected function serializeItems(): array
    {
        return array_map(static fn (User $user) => $user->toAdminArray(), $this->items);
    }
}
