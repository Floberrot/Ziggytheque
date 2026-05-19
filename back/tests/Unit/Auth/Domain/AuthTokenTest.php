<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Domain;

use App\Auth\Domain\AuthToken;
use App\Auth\Domain\AuthTokenTypeEnum;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRoleEnum;
use App\Auth\Domain\UserStatusEnum;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AuthTokenTest extends TestCase
{
    private function makeUser(): User
    {
        return new User(
            id: 'user-id-1',
            email: 'user@example.com',
            passwordHash: 'hash',
            displayName: 'Test User',
            role: UserRoleEnum::User,
            status: UserStatusEnum::Active,
        );
    }

    private function makeToken(
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $consumedAt = null,
    ): AuthToken {
        $token = new AuthToken(
            id: 'token-id-1',
            user: $this->makeUser(),
            type: AuthTokenTypeEnum::EmailVerification,
            tokenHash: 'sha256hash',
            expiresAt: $expiresAt,
        );

        if ($consumedAt !== null) {
            $token->consumedAt = $consumedAt;
        }

        return $token;
    }

    public function testIsValidWhenNotConsumedAndNotExpired(): void
    {
        $token = $this->makeToken(expiresAt: new DateTimeImmutable('+1 hour'));
        $this->assertTrue($token->isValid());
    }

    public function testIsInvalidWhenExpired(): void
    {
        $token = $this->makeToken(expiresAt: new DateTimeImmutable('-1 second'));
        $this->assertFalse($token->isValid());
    }

    public function testIsInvalidWhenConsumed(): void
    {
        $token = $this->makeToken(
            expiresAt: new DateTimeImmutable('+1 hour'),
            consumedAt: new DateTimeImmutable(),
        );
        $this->assertFalse($token->isValid());
    }

    public function testConsumeSetsConsumedAt(): void
    {
        $token = $this->makeToken(expiresAt: new DateTimeImmutable('+1 hour'));
        $this->assertNull($token->consumedAt);

        $token->consume();

        $this->assertNotNull($token->consumedAt);
        $this->assertFalse($token->isValid());
    }
}
