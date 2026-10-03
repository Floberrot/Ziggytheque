<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application\Admin\GenerateResetLink;

use App\Auth\Application\Admin\GenerateResetLink\GenerateResetLinkCommand;
use App\Auth\Application\Admin\GenerateResetLink\GenerateResetLinkHandler;
use App\Auth\Domain\AuthToken;
use App\Auth\Domain\AuthTokenRepositoryInterface;
use App\Auth\Domain\AuthTokenTypeEnum;
use App\Auth\Domain\Exception\UserNotFoundException;
use App\Auth\Domain\Service\TokenGeneratorInterface;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GenerateResetLinkHandlerTest extends TestCase
{
    private function tokenGenerator(): TokenGeneratorInterface
    {
        $tokenGenerator = $this->createStub(TokenGeneratorInterface::class);
        $tokenGenerator->method('generate')->willReturn('plain-token');
        $tokenGenerator->method('hash')->willReturnCallback(static fn (string $plain): string => 'hash-of-' . $plain);

        return $tokenGenerator;
    }

    public function testStoresOnlyTheHashAndReturnsTheLinkWithThePlainToken(): void
    {
        $account        = new User(id: 'user-1', email: 'reader@example.com', passwordHash: 'hash', displayName: 'Reader');
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($account);

        $savedTokens     = [];
        $tokenRepository = $this->createMock(AuthTokenRepositoryInterface::class);
        $tokenRepository->expects($this->once())->method('save')->willReturnCallback(
            static function (AuthToken $token) use (&$savedTokens): void {
                $savedTokens[] = $token;
            },
        );

        $link = (new GenerateResetLinkHandler($userRepository, $tokenRepository, $this->tokenGenerator(), 'https://front.example/'))(
            new GenerateResetLinkCommand('user-1'),
        );

        $this->assertSame('https://front.example/reset-password?token=plain-token', $link);
        $this->assertCount(1, $savedTokens);
        $this->assertSame($account, $savedTokens[0]->user);
        $this->assertSame(AuthTokenTypeEnum::PasswordReset, $savedTokens[0]->type);
        $this->assertSame('hash-of-plain-token', $savedTokens[0]->tokenHash);
        $this->assertGreaterThan(new DateTimeImmutable('+23 hours'), $savedTokens[0]->expiresAt);
        $this->assertLessThanOrEqual(new DateTimeImmutable('+24 hours'), $savedTokens[0]->expiresAt);
    }

    public function testAnUnknownAccountIsNotFound(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn(null);
        $tokenRepository = $this->createMock(AuthTokenRepositoryInterface::class);
        $tokenRepository->expects($this->never())->method('save');

        $this->expectException(UserNotFoundException::class);

        (new GenerateResetLinkHandler($userRepository, $tokenRepository, $this->tokenGenerator(), 'https://front.example'))(
            new GenerateResetLinkCommand('ghost'),
        );
    }
}
