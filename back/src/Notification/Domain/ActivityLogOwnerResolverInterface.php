<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use App\Auth\Domain\User;

/**
 * Resolves the User to attribute an ActivityLog to.
 *
 * Implemented in Auth Infrastructure (Symfony Security token). In worker /
 * scheduler contexts there is no security token, so currentOwner() returns null.
 */
interface ActivityLogOwnerResolverInterface
{
    public function currentOwner(): ?User;

    public function findById(string $userId): ?User;
}
