<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class MonitorUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @phpstan-param non-empty-string $username
     * @phpstan-param non-empty-string $password
     */
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

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
