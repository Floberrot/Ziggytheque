<?php

declare(strict_types=1);

namespace App\Auth\Shared\Event;

use App\Shared\Domain\Event\SucceededEventInterface;

/**
 * Carries no verification token: activity-log metadata is built from event
 * properties and must never contain credentials (see UserRegisteredEvent for
 * the email-sending flow, which never reaches the journal).
 */
final readonly class RegisterSucceededEvent implements SucceededEventInterface
{
    public function __construct(
        public string $correlationId,
        public string $userId,
        public string $email,
        public string $displayName,
    ) {
    }
}
