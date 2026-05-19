<?php

declare(strict_types=1);

namespace App\Auth\Application\Admin\ListUsers;

use App\Auth\Domain\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListUsersHandler
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    public function __invoke(ListUsersQuery $query): UserListResult
    {
        $result = $this->userRepository->findPaginated(
            search: $query->search,
            status: $query->status,
            page: $query->page,
            limit: $query->limit,
        );

        return new UserListResult(
            items: $result['items'],
            total: $result['total'],
            page: $query->page,
            limit: $query->limit,
        );
    }
}
