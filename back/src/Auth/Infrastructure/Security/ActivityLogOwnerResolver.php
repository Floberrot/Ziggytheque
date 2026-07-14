<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Security;

use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Notification\Domain\ActivityLogOwnerResolverInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Resolves the ActivityLog owner from the Symfony security token (HTTP
 * context) or by id (domain events carrying a userId). In worker / scheduler
 * contexts there is no token, so currentOwner() returns null.
 */
final readonly class ActivityLogOwnerResolver implements ActivityLogOwnerResolverInterface
{
    public function __construct(
        private Security $security,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function currentOwner(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    public function findById(string $userId): ?User
    {
        return $this->userRepository->findById($userId);
    }
}
