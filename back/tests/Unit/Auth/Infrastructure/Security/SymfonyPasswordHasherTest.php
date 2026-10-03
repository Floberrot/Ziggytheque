<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Infrastructure\Security;

use App\Auth\Domain\User;
use App\Auth\Infrastructure\Security\SymfonyPasswordHasher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;

final class SymfonyPasswordHasherTest extends TestCase
{
    private function hasher(): SymfonyPasswordHasher
    {
        // The production algorithm (bcrypt) at its lowest cost, to keep the test fast.
        $factory = new PasswordHasherFactory([User::class => ['algorithm' => 'bcrypt', 'cost' => 4]]);

        return new SymfonyPasswordHasher($factory, new UserPasswordHasher($factory));
    }

    private function accountWithHash(string $passwordHash): User
    {
        return new User(id: 'user-1', email: 'reader@example.com', passwordHash: $passwordHash, displayName: 'Reader');
    }

    public function testAHashIsNeverThePlainPassword(): void
    {
        $hash = $this->hasher()->hash('Password1!');

        $this->assertNotSame('Password1!', $hash);
        $this->assertStringStartsWith('$2y$', $hash);
    }

    public function testTheStoredHashAcceptsOnlyItsOwnPassword(): void
    {
        $hasher  = $this->hasher();
        $account = $this->accountWithHash($hasher->hash('Password1!'));

        $this->assertTrue($hasher->isValid($account, 'Password1!'));
        $this->assertFalse($hasher->isValid($account, 'password1!'));
    }

    public function testAnAccountWithoutPasswordAcceptsNothing(): void
    {
        $this->assertFalse($this->hasher()->isValid($this->accountWithHash(''), ''));
    }
}
