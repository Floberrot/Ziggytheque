<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application\ResetPassword;

use App\Auth\Application\ResetPassword\ResetPasswordCommand;
use App\Auth\Application\ResetPassword\ResetPasswordHandler;
use App\Auth\Domain\AuthToken;
use App\Auth\Domain\AuthTokenRepositoryInterface;
use App\Auth\Domain\AuthTokenTypeEnum;
use App\Auth\Domain\Exception\InvalidTokenException;
use App\Auth\Domain\Service\PasswordHasherInterface;
use App\Auth\Domain\Service\TokenGeneratorInterface;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ResetPasswordHandlerTest extends TestCase
{
    private function tokenGenerator(): TokenGeneratorInterface
    {
        $tokenGenerator = $this->createStub(TokenGeneratorInterface::class);
        $tokenGenerator->method('hash')->willReturnCallback(static fn (string $plain): string => 'hash-of-' . $plain);

        return $tokenGenerator;
    }

    public function testAValidTokenIsSpentAndSetsTheNewPassword(): void
    {
        $account   = new User(id: 'user-1', email: 'reader@example.com', passwordHash: 'old-hash', displayName: 'Reader');
        $authToken = new AuthToken(
            id: 'token-1',
            user: $account,
            type: AuthTokenTypeEnum::PasswordReset,
            tokenHash: 'hash-of-plain-token',
            expiresAt: new DateTimeImmutable('+1 hour'),
        );
        $tokenRepository = $this->createMock(AuthTokenRepositoryInterface::class);
        $tokenRepository->expects($this->once())
            ->method('findValidByHash')
            ->with('hash-of-plain-token', AuthTokenTypeEnum::PasswordReset)
            ->willReturn($authToken);
        $tokenRepository->expects($this->once())->method('save')->with($authToken);
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())->method('save')->with($account);
        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher->expects($this->once())->method('hash')->with('NewPassword1!')->willReturn('new-hash');

        (new ResetPasswordHandler($tokenRepository, $userRepository, $this->tokenGenerator(), $passwordHasher))(
            new ResetPasswordCommand('plain-token', 'NewPassword1!'),
        );

        $this->assertSame('new-hash', $account->passwordHash);
        $this->assertNotNull($authToken->consumedAt);
        $this->assertFalse($authToken->isValid());
    }

    public function testAnUnknownOrSpentTokenIsRefused(): void
    {
        $tokenRepository = $this->createMock(AuthTokenRepositoryInterface::class);
        $tokenRepository->expects($this->once())->method('findValidByHash')->willReturn(null);
        $tokenRepository->expects($this->never())->method('save');
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->never())->method('save');
        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher->expects($this->never())->method('hash');

        $this->expectException(InvalidTokenException::class);

        (new ResetPasswordHandler($tokenRepository, $userRepository, $this->tokenGenerator(), $passwordHasher))(
            new ResetPasswordCommand('stale-token', 'NewPassword1!'),
        );
    }
}
