<?php

declare(strict_types=1);

namespace App\Auth\Domain\Service;

use App\Auth\Domain\User;

/** Issues the bearer token a signed-in account sends with every request. */
interface SessionTokenIssuerInterface
{
    public function issue(User $user): string;

    /** A session token that also opens the admin area (the gate's second factor). */
    public function issueAdminUnlocked(User $user): string;
}
