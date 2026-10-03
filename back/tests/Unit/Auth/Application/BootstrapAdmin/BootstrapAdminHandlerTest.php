<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application\BootstrapAdmin;

use App\Auth\Application\BootstrapAdmin\BootstrapAdminCommand;
use App\Auth\Application\BootstrapAdmin\BootstrapAdminHandler;
use App\Auth\Domain\Exception\EmailAlreadyTakenException;
use App\Auth\Domain\Service\AdminBackfillServiceInterface;
use App\Auth\Domain\Service\PasswordHasherInterface;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Domain\UserRoleEnum;
use App\Auth\Domain\UserStatusEnum;
use App\Auth\Domain\ValueObject\BackfillReport;
use PHPUnit\Framework\TestCase;

final class BootstrapAdminHandlerTest extends TestCase
{
    private function passwordHasher(): PasswordHasherInterface
    {
        $passwordHasher = $this->createStub(PasswordHasherInterface::class);
        $passwordHasher->method('hash')->willReturn('admin-hash');

        return $passwordHasher;
    }

    public function testCreatesAnActiveAdminAndHandsItTheOrphanedData(): void
    {
        $report = new BackfillReport(collectionEntries: 3, notifications: 1, articles: 2, activityLogs: 4);

        $savedAccounts  = [];
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())->method('hasAnyAdmin')->willReturn(false);
        $userRepository->expects($this->once())->method('save')->willReturnCallback(
            static function (User $user) use (&$savedAccounts): void {
                $savedAccounts[] = $user;
            },
        );
        $backfillService = $this->createMock(AdminBackfillServiceInterface::class);
        $backfillService->expects($this->once())
            ->method('assignAllOrphans')
            ->with($this->callback(static function (string $adminId) use (&$savedAccounts): bool {
                return $adminId === $savedAccounts[0]->id;
            }))
            ->willReturn($report);

        $result = (new BootstrapAdminHandler($userRepository, $this->passwordHasher(), $backfillService))(
            new BootstrapAdminCommand('Admin@Example.com', 'Password1!', 'Admin', force: false),
        );

        $this->assertSame($report, $result);
        $this->assertCount(1, $savedAccounts);
        $this->assertSame('admin@example.com', $savedAccounts[0]->email);
        $this->assertSame('admin-hash', $savedAccounts[0]->passwordHash);
        $this->assertSame(UserRoleEnum::Admin, $savedAccounts[0]->role);
        $this->assertSame(UserStatusEnum::Active, $savedAccounts[0]->status);
    }

    public function testRefusesASecondAdminWithoutForce(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())->method('hasAnyAdmin')->willReturn(true);
        $userRepository->expects($this->never())->method('save');
        $backfillService = $this->createMock(AdminBackfillServiceInterface::class);
        $backfillService->expects($this->never())->method('assignAllOrphans');

        $this->expectException(EmailAlreadyTakenException::class);

        (new BootstrapAdminHandler($userRepository, $this->passwordHasher(), $backfillService))(
            new BootstrapAdminCommand('admin@example.com', 'Password1!', 'Admin', force: false),
        );
    }

    public function testForceCreatesAnotherAdminWithoutAskingWhetherOneExists(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->never())->method('hasAnyAdmin');
        $userRepository->expects($this->once())->method('save');
        $backfillService = $this->createStub(AdminBackfillServiceInterface::class);
        $backfillService->method('assignAllOrphans')->willReturn(new BackfillReport(0, 0, 0, 0));

        $result = (new BootstrapAdminHandler($userRepository, $this->passwordHasher(), $backfillService))(
            new BootstrapAdminCommand('second@example.com', 'Password1!', 'Second', force: true),
        );

        $this->assertSame(0, $result->total());
    }
}
