<?php

declare(strict_types=1);

namespace App\Auth\Domain;

use Symfony\Component\Security\Core\User\UserInterface;

final class GateUser implements UserInterface
{
    /** @phpstan-param non-empty-string $identifier */
    public function __construct(public readonly string $identifier = 'gate')
    {
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
        return $this->identifier;
    }
}
