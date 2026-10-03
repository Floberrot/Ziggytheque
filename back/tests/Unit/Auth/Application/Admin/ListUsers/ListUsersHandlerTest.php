<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application\Admin\ListUsers;

use App\Auth\Application\Admin\ListUsers\ListUsersHandler;
use App\Auth\Application\Admin\ListUsers\ListUsersQuery;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Domain\UserStatusEnum;
use PHPUnit\Framework\TestCase;

final class ListUsersHandlerTest extends TestCase
{
    public function testPassesTheFiltersAndWrapsThePageInAPaginatedResult(): void
    {
        $account        = new User(id: 'user-1', email: 'reader@example.com', passwordHash: 'hash', displayName: 'Reader');
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())
            ->method('findPaginated')
            ->with('read', UserStatusEnum::PendingAdminApproval, 2, 10)
            ->willReturn(['items' => [$account], 'total' => 11]);

        $result = (new ListUsersHandler($userRepository))(
            new ListUsersQuery(search: 'read', status: UserStatusEnum::PendingAdminApproval, page: 2, limit: 10),
        );

        $array = $result->toArray();
        $this->assertSame(11, $array['total']);
        $this->assertSame(2, $array['page']);
        $this->assertSame(10, $array['limit']);
        $this->assertSame([$account->toAdminArray()], $array['items']);
    }
}
