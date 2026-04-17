<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Infrastructure\Http;

use App\Auth\Domain\GateUser;
use App\Auth\Infrastructure\Http\GateUserProvider;
use App\Auth\Infrastructure\Http\MonitorUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class GateUserProviderTest extends TestCase
{
    private GateUserProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new GateUserProvider('monitor', 'monpass');
    }

    public function testLoadsGateUser(): void
    {
        $user = $this->provider->loadUserByIdentifier('gate');

        $this->assertInstanceOf(GateUser::class, $user);
        $this->assertSame('gate', $user->getUserIdentifier());
    }

    public function testLoadsMonitorUser(): void
    {
        $user = $this->provider->loadUserByIdentifier('monitor');

        $this->assertInstanceOf(MonitorUser::class, $user);
    }

    public function testThrowsForUnknownIdentifier(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->provider->loadUserByIdentifier('unknown');
    }

    public function testSupportsGateUser(): void
    {
        $this->assertTrue($this->provider->supportsClass(GateUser::class));
    }

    public function testSupportsMonitorUser(): void
    {
        $this->assertTrue($this->provider->supportsClass(MonitorUser::class));
    }

    public function testDoesNotSupportOtherClasses(): void
    {
        $this->assertFalse($this->provider->supportsClass(\stdClass::class));
    }

    public function testRefreshGateUser(): void
    {
        $user = new GateUser();
        $refreshed = $this->provider->refreshUser($user);

        $this->assertInstanceOf(GateUser::class, $refreshed);
    }

    public function testRefreshThrowsForUnsupportedUser(): void
    {
        $this->expectException(UnsupportedUserException::class);
        $this->provider->refreshUser($this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class));
    }
}
