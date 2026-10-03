<?php

declare(strict_types=1);

namespace App\Auth\Domain\Service;

use App\Auth\Domain\User;

interface PasswordHasherInterface
{
    /**
     * The hash to store for this password. Also called on purpose with a hash that is
     * thrown away, so an unknown address costs as much time as a known one.
     */
    public function hash(string $plainPassword): string;

    public function isValid(User $user, string $plainPassword): bool;
}
