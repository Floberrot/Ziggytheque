<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Infrastructure\Token;

use App\Auth\Domain\User;
use App\Auth\Infrastructure\Token\JwtSessionTokenIssuer;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;

final class JwtSessionTokenIssuerTest extends TestCase
{
    private function account(): User
    {
        return new User(id: 'user-1', email: 'reader@example.com', passwordHash: 'hash', displayName: 'Reader');
    }

    public function testASessionTokenIsAPlainJwtForTheAccount(): void
    {
        $account    = $this->account();
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->expects($this->once())->method('create')->with($account)->willReturn('plain-jwt');
        $jwtManager->expects($this->never())->method('createFromPayload');

        $this->assertSame('plain-jwt', (new JwtSessionTokenIssuer($jwtManager))->issue($account));
    }

    public function testTheAdminTokenCarriesTheClaimTheUserProviderReads(): void
    {
        $account    = $this->account();
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->expects($this->once())
            ->method('createFromPayload')
            ->with($account, [JwtSessionTokenIssuer::ADMIN_UNLOCKED_CLAIM => true])
            ->willReturn('unlocked-jwt');

        $this->assertSame('unlocked-jwt', (new JwtSessionTokenIssuer($jwtManager))->issueAdminUnlocked($account));
        $this->assertSame('adminUnlocked', JwtSessionTokenIssuer::ADMIN_UNLOCKED_CLAIM);
    }
}
