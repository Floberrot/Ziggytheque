<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application\Login;

use App\Auth\Application\Login\LoginCommand;
use App\Auth\Application\Login\LoginHandler;
use App\Auth\Domain\Exception\AccountNotActivatedException;
use App\Auth\Domain\Exception\InvalidCredentialsException;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Domain\UserStatusEnum;
use App\Shared\Application\Bus\EventBusInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final class LoginHandlerTest extends TestCase
{
    private function account(UserStatusEnum $status = UserStatusEnum::Active): User
    {
        return new User(
            id: 'user-1',
            email: 'reader@example.com',
            passwordHash: 'hashed',
            displayName: 'Reader',
            status: $status,
        );
    }

    private function handler(
        ?User $storedAccount,
        bool $passwordMatches,
        PasswordHasherFactoryInterface $passwordHasherFactory,
    ): LoginHandler {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findByEmail')->willReturn($storedAccount);

        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('isPasswordValid')->willReturn($passwordMatches);

        $tokenManager = $this->createStub(JWTTokenManagerInterface::class);
        $tokenManager->method('create')->willReturn('session-token');

        return new LoginHandler(
            $userRepository,
            $passwordHasher,
            $passwordHasherFactory,
            $tokenManager,
            $this->createStub(EventBusInterface::class),
        );
    }

    public function testAnUnknownAddressCostsAPasswordHashBeforeBeingRefused(): void
    {
        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects($this->once())->method('hash')->with('guess')->willReturn('wasted-hash');
        $factory = $this->createMock(PasswordHasherFactoryInterface::class);
        $factory->expects($this->once())->method('getPasswordHasher')->with(User::class)->willReturn($hasher);

        $this->expectException(InvalidCredentialsException::class);

        ($this->handler(null, false, $factory))(new LoginCommand('nobody@example.com', 'guess'));
    }

    public function testAWrongPasswordIsRefusedAfterItsOwnCheck(): void
    {
        $factory = $this->createMock(PasswordHasherFactoryInterface::class);
        $factory->expects($this->never())->method('getPasswordHasher');

        $this->expectException(InvalidCredentialsException::class);

        ($this->handler($this->account(), false, $factory))(new LoginCommand('reader@example.com', 'guess'));
    }

    public function testAnInactiveAccountIsRefusedWithTheRightPassword(): void
    {
        $this->expectException(AccountNotActivatedException::class);

        ($this->handler(
            $this->account(UserStatusEnum::PendingAdminApproval),
            true,
            $this->createStub(PasswordHasherFactoryInterface::class),
        ))(new LoginCommand('reader@example.com', 'right-password'));
    }

    public function testTheRightPasswordOpensASession(): void
    {
        $account = $this->account();

        $token = ($this->handler($account, true, $this->createStub(PasswordHasherFactoryInterface::class)))(
            new LoginCommand('reader@example.com', 'right-password'),
        );

        $this->assertSame('session-token', $token);
        $this->assertNotNull($account->lastLoginAt);
    }
}
