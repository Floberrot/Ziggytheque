<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application\Gate;

use App\Auth\Application\Gate\GateCommand;
use App\Auth\Application\Gate\GateHandler;
use App\Auth\Domain\Exception\GateDisabledException;
use App\Auth\Domain\Exception\InvalidGatePasswordException;
use App\Auth\Domain\Service\SessionTokenIssuerInterface;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRoleEnum;
use App\Auth\Domain\UserStatusEnum;
use App\Auth\Shared\Event\GateFailedEvent;
use App\Auth\Shared\Event\GateStartedEvent;
use App\Auth\Shared\Event\GateSucceededEvent;
use App\Tests\Doubles\Shared\RecordingEventBus;
use PHPUnit\Framework\TestCase;

final class GateHandlerTest extends TestCase
{
    private RecordingEventBus $eventBus;

    protected function setUp(): void
    {
        $this->eventBus = new RecordingEventBus();
    }

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

    private function handler(string $gatePassword, ?SessionTokenIssuerInterface $sessionTokenIssuer = null): GateHandler
    {
        if ($sessionTokenIssuer === null) {
            $sessionTokenIssuer = $this->createStub(SessionTokenIssuerInterface::class);
            $sessionTokenIssuer->method('issueAdminUnlocked')->willReturn('unlocked-token');
        }

        return new GateHandler($gatePassword, $sessionTokenIssuer, $this->eventBus);
    }

    public function testTheRightPasswordUnlocksTheAdminArea(): void
    {
        $admin = $this->admin();
        $sessionTokenIssuer = $this->createMock(SessionTokenIssuerInterface::class);
        $sessionTokenIssuer->expects($this->once())->method('issueAdminUnlocked')->with($admin)->willReturn('unlocked-token');
        $sessionTokenIssuer->expects($this->never())->method('issue');

        $token = ($this->handler('a-strong-gate-password', $sessionTokenIssuer))(
            new GateCommand('a-strong-gate-password', $admin),
        );

        $this->assertSame('unlocked-token', $token);
        $this->assertSame([GateStartedEvent::class, GateSucceededEvent::class], $this->eventBus->eventClasses());
        $this->assertSame('admin-1', $this->eventBus->first(GateSucceededEvent::class)->userId);
    }

    public function testAWrongPasswordIsRefused(): void
    {
        try {
            ($this->handler('a-strong-gate-password'))(new GateCommand('guess', $this->admin()));
            $this->fail('A wrong gate password must be refused.');
        } catch (InvalidGatePasswordException) {
        }

        $this->assertSame([GateStartedEvent::class, GateFailedEvent::class], $this->eventBus->eventClasses());
        $this->assertSame(InvalidGatePasswordException::class, $this->eventBus->first(GateFailedEvent::class)->exceptionClass);
    }

    /** Even typing the placeholder itself must not unlock anything. */
    public function testAWeakGatePasswordKeepsTheGateClosed(): void
    {
        $this->expectException(GateDisabledException::class);

        ($this->handler('CHANGEME'))(new GateCommand('CHANGEME', $this->admin()));
    }
}
