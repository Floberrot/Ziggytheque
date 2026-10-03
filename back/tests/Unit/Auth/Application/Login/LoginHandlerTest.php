<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application\Login;

use App\Auth\Application\Login\LoginCommand;
use App\Auth\Application\Login\LoginHandler;
use App\Auth\Domain\Exception\AccountNotActivatedException;
use App\Auth\Domain\Exception\InvalidCredentialsException;
use App\Auth\Domain\Service\PasswordHasherInterface;
use App\Auth\Domain\Service\SessionTokenIssuerInterface;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Domain\UserStatusEnum;
use App\Auth\Shared\Event\LoginFailedEvent;
use App\Auth\Shared\Event\LoginStartedEvent;
use App\Auth\Shared\Event\LoginSucceededEvent;
use App\Tests\Doubles\Shared\RecordingEventBus;
use PHPUnit\Framework\TestCase;

final class LoginHandlerTest extends TestCase
{
    private RecordingEventBus $eventBus;

    protected function setUp(): void
    {
        $this->eventBus = new RecordingEventBus();
    }

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

    private function handler(?User $storedAccount, PasswordHasherInterface $passwordHasher): LoginHandler
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findByEmail')->willReturn($storedAccount);

        $sessionTokenIssuer = $this->createStub(SessionTokenIssuerInterface::class);
        $sessionTokenIssuer->method('issue')->willReturn('session-token');

        return new LoginHandler($userRepository, $passwordHasher, $sessionTokenIssuer, $this->eventBus);
    }

    private function hasherAccepting(bool $passwordMatches): PasswordHasherInterface
    {
        $passwordHasher = $this->createStub(PasswordHasherInterface::class);
        $passwordHasher->method('isValid')->willReturn($passwordMatches);

        return $passwordHasher;
    }

    public function testAnUnknownAddressCostsAPasswordHashBeforeBeingRefused(): void
    {
        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher->expects($this->once())->method('hash')->with('guess')->willReturn('wasted-hash');
        $passwordHasher->expects($this->never())->method('isValid');

        try {
            ($this->handler(null, $passwordHasher))(new LoginCommand('nobody@example.com', 'guess'));
            $this->fail('An unknown address must be refused.');
        } catch (InvalidCredentialsException) {
        }

        $this->assertSame([LoginStartedEvent::class, LoginFailedEvent::class], $this->eventBus->eventClasses());
        $this->assertSame('nobody@example.com', $this->eventBus->first(LoginFailedEvent::class)->email);
    }

    public function testAWrongPasswordIsRefusedAfterItsOwnCheck(): void
    {
        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher->expects($this->once())->method('isValid')->willReturn(false);
        $passwordHasher->expects($this->never())->method('hash');

        $this->expectException(InvalidCredentialsException::class);

        ($this->handler($this->account(), $passwordHasher))(new LoginCommand('reader@example.com', 'guess'));
    }

    public function testAnInactiveAccountIsRefusedWithTheRightPassword(): void
    {
        $this->expectException(AccountNotActivatedException::class);

        ($this->handler($this->account(UserStatusEnum::PendingAdminApproval), $this->hasherAccepting(true)))(
            new LoginCommand('reader@example.com', 'right-password'),
        );
    }

    public function testTheRightPasswordOpensASession(): void
    {
        $account = $this->account();

        $token = ($this->handler($account, $this->hasherAccepting(true)))(
            new LoginCommand('reader@example.com', 'right-password'),
        );

        $this->assertSame('session-token', $token);
        $this->assertNotNull($account->lastLoginAt);
        $this->assertSame([LoginStartedEvent::class, LoginSucceededEvent::class], $this->eventBus->eventClasses());
        $succeeded = $this->eventBus->first(LoginSucceededEvent::class);
        $this->assertSame('user-1', $succeeded->userId);
        $this->assertSame($this->eventBus->first(LoginStartedEvent::class)->correlationId, $succeeded->correlationId);
    }
}
