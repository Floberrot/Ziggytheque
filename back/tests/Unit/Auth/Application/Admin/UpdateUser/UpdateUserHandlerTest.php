<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application\Admin\UpdateUser;

use App\Auth\Application\Admin\UpdateUser\UpdateUserCommand;
use App\Auth\Application\Admin\UpdateUser\UpdateUserHandler;
use App\Auth\Domain\Exception\UserNotFoundException;
use App\Auth\Domain\NotificationChannelEnum;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Domain\UserStatusEnum;
use PHPUnit\Framework\TestCase;

final class UpdateUserHandlerTest extends TestCase
{
    private function account(): User
    {
        return new User(
            id: 'user-1',
            email: 'reader@example.com',
            passwordHash: 'hash',
            displayName: 'Reader',
            status: UserStatusEnum::Active,
            notificationChannel: NotificationChannelEnum::Email,
            notificationEmail: 'alerts@example.com',
            discordWebhookUrl: 'https://discord.com/api/webhooks/1/abc',
        );
    }

    private function handlerReturning(?User $storedAccount): UpdateUserHandler
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($storedAccount);

        return new UpdateUserHandler($userRepository);
    }

    public function testChangesTheGivenFieldsAndSaves(): void
    {
        $account        = $this->account();
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())->method('findById')->with('user-1')->willReturn($account);
        $userRepository->expects($this->once())->method('save')->with($account);

        $updated = (new UpdateUserHandler($userRepository))(new UpdateUserCommand(
            userId: 'user-1',
            displayName: 'Renamed',
            status: UserStatusEnum::Disabled,
            notificationChannel: null,
        ));

        $this->assertSame($account, $updated);
        $this->assertSame('Renamed', $account->displayName);
        $this->assertSame(UserStatusEnum::Disabled, $account->status);
        $this->assertSame(NotificationChannelEnum::Email, $account->notificationChannel);
    }

    public function testChangingTheChannelKeepsTheSavedDestinations(): void
    {
        $account = $this->account();

        ($this->handlerReturning($account))(new UpdateUserCommand(
            userId: 'user-1',
            displayName: null,
            status: null,
            notificationChannel: NotificationChannelEnum::Discord,
        ));

        $this->assertSame(NotificationChannelEnum::Discord, $account->notificationChannel);
        $this->assertSame('alerts@example.com', $account->notificationEmail);
        $this->assertSame('https://discord.com/api/webhooks/1/abc', $account->discordWebhookUrl);
        $this->assertSame('Reader', $account->displayName);
        $this->assertSame(UserStatusEnum::Active, $account->status);
    }

    public function testAnUnknownAccountIsNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        ($this->handlerReturning(null))(new UpdateUserCommand('ghost', 'Name', null, null));
    }
}
