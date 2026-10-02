<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Infrastructure\Http;

use App\Auth\Infrastructure\Http\MonitorUser;
use App\Auth\Infrastructure\Http\MonitorUserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class MonitorUserProviderTest extends TestCase
{
    public function testLoadsTheMonitorUser(): void
    {
        $user = (new MonitorUserProvider('monitor', 'a-strong-monitor-password'))->loadUserByIdentifier('monitor');

        $this->assertInstanceOf(MonitorUser::class, $user);
        $this->assertSame('a-strong-monitor-password', $user->getPassword());
    }

    public function testRejectsAnotherUser(): void
    {
        $this->expectException(UserNotFoundException::class);

        (new MonitorUserProvider('monitor', 'a-strong-monitor-password'))->loadUserByIdentifier('admin');
    }

    /** A default, placeholder or short password keeps the dashboard closed. */
    public function testNobodySignsInWithAWeakPassword(): void
    {
        foreach (['monitor', 'CHANGEME', '', 'short'] as $weakPassword) {
            try {
                (new MonitorUserProvider('monitor', $weakPassword))->loadUserByIdentifier('monitor');
                $this->fail(sprintf('"%s" must not open the monitor.', $weakPassword));
            } catch (UserNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testRefreshesOnlyMonitorUsers(): void
    {
        $provider = new MonitorUserProvider('monitor', 'a-strong-monitor-password');

        $refreshed = $provider->refreshUser(new MonitorUser('monitor', 'a-strong-monitor-password'));
        $this->assertSame('monitor', $refreshed->getUserIdentifier());
        $this->assertTrue($provider->supportsClass(MonitorUser::class));

        $this->expectException(UnsupportedUserException::class);
        $provider->refreshUser(new InMemoryUser('someone', 'secret'));
    }
}
