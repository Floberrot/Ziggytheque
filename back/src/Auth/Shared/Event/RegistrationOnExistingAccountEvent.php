<?php

declare(strict_types=1);

namespace App\Auth\Shared\Event;

/**
 * Someone asked to register an address that already has an account. The caller got
 * the usual "check your email" answer; the owner is told by email instead.
 */
final readonly class RegistrationOnExistingAccountEvent
{
    public function __construct(
        public string $email,
        public string $displayName,
    ) {
    }
}
