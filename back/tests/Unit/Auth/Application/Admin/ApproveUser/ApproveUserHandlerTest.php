<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application\Admin\ApproveUser;

use App\Auth\Application\Admin\ApproveUser\ApproveUserCommand;
use App\Auth\Application\Admin\ApproveUser\ApproveUserHandler;
use App\Auth\Domain\Exception\UserNotFoundException;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Domain\UserStatusEnum;
use App\Auth\Shared\Event\UserApprovedEvent;
use App\Tests\Doubles\Shared\RecordingEventBus;
use PHPUnit\Framework\TestCase;

final class ApproveUserHandlerTest extends TestCase
{
    public function testActivatesTheAccountAndAnnouncesIt(): void
    {
        $pending = new User(
            id: 'user-1',
            email: 'reader@example.com',
            passwordHash: 'hash',
            displayName: 'Reader',
            status: UserStatusEnum::PendingAdminApproval,
        );
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())->method('findById')->with('user-1')->willReturn($pending);
        $userRepository->expects($this->once())->method('save')->with($pending);
        $eventBus = new RecordingEventBus();

        (new ApproveUserHandler($userRepository, $eventBus))(new ApproveUserCommand('user-1'));

        $this->assertSame(UserStatusEnum::Active, $pending->status);
        $approved = $eventBus->first(UserApprovedEvent::class);
        $this->assertSame('user-1', $approved->userId);
        $this->assertSame('reader@example.com', $approved->email);
        $this->assertSame('Reader', $approved->displayName);
    }

    public function testAnUnknownAccountIsNotFound(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())->method('findById')->willReturn(null);
        $userRepository->expects($this->never())->method('save');
        $eventBus = new RecordingEventBus();

        try {
            (new ApproveUserHandler($userRepository, $eventBus))(new ApproveUserCommand('ghost'));
            $this->fail('An unknown account must be refused.');
        } catch (UserNotFoundException) {
        }

        $this->assertSame([], $eventBus->events);
    }
}
