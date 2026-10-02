<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Infrastructure\Security;

use App\Auth\Domain\User;
use App\Auth\Domain\UserStatusEnum;
use App\Auth\Infrastructure\Security\ActiveUserChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class ActiveUserCheckerTest extends TestCase
{
    private function makeUser(UserStatusEnum $status): User
    {
        return new User(
            id: 'user-id',
            email: 'reader@example.com',
            passwordHash: 'hash',
            displayName: 'Reader',
            status: $status,
        );
    }

    public function testAnActiveUserPasses(): void
    {
        (new ActiveUserChecker())->checkPreAuth($this->makeUser(UserStatusEnum::Active));

        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{UserStatusEnum}> */
    public static function inactiveStatuses(): iterable
    {
        yield 'disabled' => [UserStatusEnum::Disabled];
        yield 'pending approval' => [UserStatusEnum::PendingAdminApproval];
        yield 'pending email verification' => [UserStatusEnum::PendingEmailVerification];
    }

    #[DataProvider('inactiveStatuses')]
    public function testAnInactiveUserIsRefused(UserStatusEnum $status): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);

        (new ActiveUserChecker())->checkPreAuth($this->makeUser($status));
    }

    public function testAnotherKindOfUserIsLeftAlone(): void
    {
        (new ActiveUserChecker())->checkPreAuth(new InMemoryUser('monitor', 'secret'));

        $this->addToAssertionCount(1);
    }

    public function testThePostAuthenticationCheckRefusesNothing(): void
    {
        (new ActiveUserChecker())->checkPostAuth($this->makeUser(UserStatusEnum::Disabled));

        $this->addToAssertionCount(1);
    }
}
