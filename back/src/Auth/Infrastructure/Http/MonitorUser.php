<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class MonitorUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        private readonly string $username,
        private readonly string $password,
    ) {
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
