<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application\Admin\DeleteUser;

use App\Auth\Application\Admin\DeleteUser\DeleteUserCommand;
use App\Auth\Application\Admin\DeleteUser\DeleteUserHandler;
use App\Auth\Domain\Exception\UserNotFoundException;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class DeleteUserHandlerTest extends TestCase
{
    public function testDeletesTheAccount(): void
    {
        $account        = new User(id: 'user-1', email: 'reader@example.com', passwordHash: 'hash', displayName: 'Reader');
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())->method('findById')->with('user-1')->willReturn($account);
        $userRepository->expects($this->once())->method('delete')->with($account);

        (new DeleteUserHandler($userRepository))(new DeleteUserCommand('user-1'));
    }

    public function testAnUnknownAccountIsNotFound(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())->method('findById')->willReturn(null);
        $userRepository->expects($this->never())->method('delete');

        $this->expectException(UserNotFoundException::class);

        (new DeleteUserHandler($userRepository))(new DeleteUserCommand('ghost'));
    }
}
