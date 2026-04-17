<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Domain;

use App\Auth\Domain\GateUser;
use PHPUnit\Framework\TestCase;

class GateUserTest extends TestCase
{
    public function testDefaultIdentifierIsGate(): void
    {
        $user = new GateUser();

        $this->assertSame('gate', $user->getUserIdentifier());
    }

    public function testCustomIdentifier(): void
    {
        $user = new GateUser('monitor');

        $this->assertSame('monitor', $user->getUserIdentifier());
    }

    public function testHasRoleUser(): void
    {
        $user = new GateUser();

        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testEraseCredentialsDoesNothing(): void
    {
        $user = new GateUser();

        $user->eraseCredentials();

        $this->assertSame('gate', $user->getUserIdentifier());
    }
}
