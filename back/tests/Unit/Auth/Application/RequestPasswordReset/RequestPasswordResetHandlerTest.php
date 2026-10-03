<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application\RequestPasswordReset;

use App\Auth\Application\RequestPasswordReset\RequestPasswordResetCommand;
use App\Auth\Application\RequestPasswordReset\RequestPasswordResetHandler;
use App\Auth\Domain\AuthToken;
use App\Auth\Domain\AuthTokenRepositoryInterface;
use App\Auth\Domain\AuthTokenTypeEnum;
use App\Auth\Domain\Service\TokenGeneratorInterface;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Shared\Event\PasswordResetRequestedEvent;
use App\Tests\Doubles\Shared\RecordingEventBus;
use PHPUnit\Framework\TestCase;

final class RequestPasswordResetHandlerTest extends TestCase
{
    private function tokenGenerator(): TokenGeneratorInterface
    {
        $tokenGenerator = $this->createStub(TokenGeneratorInterface::class);
        $tokenGenerator->method('generate')->willReturn('plain-token');
        $tokenGenerator->method('hash')->willReturn('token-hash');

        return $tokenGenerator;
    }

    public function testAKnownAddressGetsAResetTokenAndAnEmail(): void
    {
        $account        = new User(id: 'user-1', email: 'reader@example.com', passwordHash: 'hash', displayName: 'Reader');
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())->method('findByEmail')->with('reader@example.com')->willReturn($account);
        $tokenRepository = $this->createMock(AuthTokenRepositoryInterface::class);
        $tokenRepository->expects($this->once())->method('save')->with($this->callback(
            static fn (AuthToken $token): bool => $token->user === $account
                && $token->type === AuthTokenTypeEnum::PasswordReset
                && $token->tokenHash === 'token-hash',
        ));
        $eventBus = new RecordingEventBus();

        (new RequestPasswordResetHandler($userRepository, $tokenRepository, $this->tokenGenerator(), $eventBus, 'https://front.example/'))(
            new RequestPasswordResetCommand('reader@example.com'),
        );

        $requested = $eventBus->first(PasswordResetRequestedEvent::class);
        $this->assertSame('user-1', $requested->userId);
        $this->assertSame('reader@example.com', $requested->email);
        $this->assertSame('plain-token', $requested->resetTokenPlain);
        $this->assertSame('https://front.example/reset-password?token=plain-token', $requested->resetUrl);
    }

    /** The caller gets the same answer either way: nothing tells an address has no account. */
    public function testAnUnknownAddressIsIgnoredQuietly(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findByEmail')->willReturn(null);
        $tokenRepository = $this->createMock(AuthTokenRepositoryInterface::class);
        $tokenRepository->expects($this->never())->method('save');
        $eventBus = new RecordingEventBus();

        (new RequestPasswordResetHandler($userRepository, $tokenRepository, $this->tokenGenerator(), $eventBus, 'https://front.example'))(
            new RequestPasswordResetCommand('nobody@example.com'),
        );

        $this->assertSame([], $eventBus->events);
    }
}
