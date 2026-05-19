<?php

declare(strict_types=1);

namespace App\Shared\Domain\Security;

use App\Shared\Domain\Exception\DomainException;

interface CurrentUserProviderInterface
{
    /** @throws DomainException if no authenticated user */
    public function currentUserId(): string;

    public function currentUserIdOrNull(): ?string;

    public function isAdmin(): bool;

    public function isAdminUnlocked(): bool;
}
