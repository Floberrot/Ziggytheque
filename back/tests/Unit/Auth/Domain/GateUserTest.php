<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Domain;

use App\Auth\Domain\GateUser;
use PHPUnit\Framework\TestCase;

final class GateUserTest extends TestCase
{
    public function testDefaultIdentifier(): void
    {
        $user = new GateUser();
        $this->assertSame('gate', $user->getUserIdentifier());
    }

    public function testCustomIdentifier(): void
    {
        $user = new GateUser('admin');
        $this->assertSame('admin', $user->getUserIdentifier());
    }

    public function testRoles(): void
    {
        $user = new GateUser();
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testEraseCredentialsDoesNothing(): void
    {
        $user = new GateUser();
        $user->eraseCredentials();
        $this->assertSame('gate', $user->getUserIdentifier());
    }
}
