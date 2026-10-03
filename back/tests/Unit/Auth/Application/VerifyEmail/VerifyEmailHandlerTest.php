<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application\VerifyEmail;

use App\Auth\Application\VerifyEmail\VerifyEmailCommand;
use App\Auth\Application\VerifyEmail\VerifyEmailHandler;
use App\Auth\Domain\AuthToken;
use App\Auth\Domain\AuthTokenRepositoryInterface;
use App\Auth\Domain\AuthTokenTypeEnum;
use App\Auth\Domain\Exception\InvalidTokenException;
use App\Auth\Domain\Service\TokenGeneratorInterface;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Domain\UserStatusEnum;
use App\Auth\Shared\Event\UserEmailVerifiedEvent;
use App\Tests\Doubles\Shared\RecordingEventBus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class VerifyEmailHandlerTest extends TestCase
{
    private function tokenGenerator(): TokenGeneratorInterface
    {
        $tokenGenerator = $this->createStub(TokenGeneratorInterface::class);
        $tokenGenerator->method('hash')->willReturnCallback(static fn (string $plain): string => 'hash-of-' . $plain);

        return $tokenGenerator;
    }

    public function testAValidTokenSendsTheAccountToAdminApproval(): void
    {
        $account   = new User(id: 'user-1', email: 'reader@example.com', passwordHash: 'hash', displayName: 'Reader');
        $authToken = new AuthToken(
            id: 'token-1',
            user: $account,
            type: AuthTokenTypeEnum::EmailVerification,
            tokenHash: 'hash-of-plain-token',
            expiresAt: new DateTimeImmutable('+1 day'),
        );
        $tokenRepository = $this->createMock(AuthTokenRepositoryInterface::class);
        $tokenRepository->expects($this->once())
            ->method('findValidByHash')
            ->with('hash-of-plain-token', AuthTokenTypeEnum::EmailVerification)
            ->willReturn($authToken);
        $tokenRepository->expects($this->once())->method('save')->with($authToken);
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())->method('save')->with($account);
        $eventBus = new RecordingEventBus();

        (new VerifyEmailHandler($tokenRepository, $userRepository, $this->tokenGenerator(), $eventBus))(
            new VerifyEmailCommand('plain-token'),
        );

        $this->assertSame(UserStatusEnum::PendingAdminApproval, $account->status);
        $this->assertFalse($authToken->isValid());
        $verified = $eventBus->first(UserEmailVerifiedEvent::class);
        $this->assertSame('user-1', $verified->userId);
        $this->assertSame('reader@example.com', $verified->email);
    }

    public function testAnUnknownOrSpentTokenIsRefused(): void
    {
        $tokenRepository = $this->createMock(AuthTokenRepositoryInterface::class);
        $tokenRepository->expects($this->once())->method('findValidByHash')->willReturn(null);
        $tokenRepository->expects($this->never())->method('save');
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->never())->method('save');
        $eventBus = new RecordingEventBus();

        try {
            (new VerifyEmailHandler($tokenRepository, $userRepository, $this->tokenGenerator(), $eventBus))(
                new VerifyEmailCommand('stale-token'),
            );
            $this->fail('A stale token must be refused.');
        } catch (InvalidTokenException) {
        }

        $this->assertSame([], $eventBus->events);
    }
}
