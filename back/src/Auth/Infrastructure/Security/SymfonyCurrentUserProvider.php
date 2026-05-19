<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Security;

use App\Auth\Domain\User;
use App\Auth\Domain\UserRoleEnum;
use App\Shared\Domain\Security\CurrentUserProviderInterface;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class SymfonyCurrentUserProvider implements CurrentUserProviderInterface
{
    public function __construct(private Security $security)
    {
    }

    public function currentUserId(): string
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new RuntimeException('No authenticated user found.');
        }

        return $user->id;
    }

    public function currentUserIdOrNull(): ?string
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user->id : null;
    }

    public function isAdmin(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User && $user->role === UserRoleEnum::Admin;
    }

    public function isAdminUnlocked(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN_UNLOCKED');
    }
}
