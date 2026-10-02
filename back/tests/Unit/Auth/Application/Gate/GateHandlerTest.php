<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application\Gate;

use App\Auth\Application\Gate\GateCommand;
use App\Auth\Application\Gate\GateHandler;
use App\Auth\Domain\Exception\GateDisabledException;
use App\Auth\Domain\Exception\InvalidGatePasswordException;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRoleEnum;
use App\Auth\Domain\UserStatusEnum;
use App\Shared\Application\Bus\EventBusInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;

final class GateHandlerTest extends TestCase
{
    private function admin(): User
    {
        return new User(
            id: 'admin-1',
            email: 'admin@example.com',
            passwordHash: 'hashed',
            displayName: 'Admin',
            role: UserRoleEnum::Admin,
            status: UserStatusEnum::Active,
        );
    }

    private function handler(string $gatePassword): GateHandler
    {
        $tokenManager = $this->createStub(JWTTokenManagerInterface::class);
        $tokenManager->method('createFromPayload')->willReturn('unlocked-token');

        return new GateHandler($gatePassword, $tokenManager, $this->createStub(EventBusInterface::class));
    }

    public function testTheRightPasswordUnlocksTheAdminArea(): void
    {
        $token = ($this->handler('a-strong-gate-password'))(new GateCommand('a-strong-gate-password', $this->admin()));

        $this->assertSame('unlocked-token', $token);
    }

    public function testAWrongPasswordIsRefused(): void
    {
        $this->expectException(InvalidGatePasswordException::class);

        ($this->handler('a-strong-gate-password'))(new GateCommand('guess', $this->admin()));
    }

    /** Even typing the placeholder itself must not unlock anything. */
    public function testAWeakGatePasswordKeepsTheGateClosed(): void
    {
        $this->expectException(GateDisabledException::class);

        ($this->handler('CHANGEME'))(new GateCommand('CHANGEME', $this->admin()));
    }
}
