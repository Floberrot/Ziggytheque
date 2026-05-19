<?php

declare(strict_types=1);

namespace App\Auth\Application\Gate;

use App\Auth\Domain\User;

final readonly class GateCommand
{
    public function __construct(
        public string $password,
        public User $user,
    ) {
    }
}
