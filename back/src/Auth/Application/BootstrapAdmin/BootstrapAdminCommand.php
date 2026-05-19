<?php

declare(strict_types=1);

namespace App\Auth\Application\BootstrapAdmin;

final readonly class BootstrapAdminCommand
{
    public function __construct(
        public string $email,
        public string $password,
        public string $displayName,
        public bool $force,
    ) {
    }
}
