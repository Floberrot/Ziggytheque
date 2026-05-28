<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use App\Shared\Domain\Exception\NotFoundException;

interface TestNotificationRecipientResolverInterface
{
    /** @throws NotFoundException when no user matches the given id */
    public function resolve(string $userId): TestNotificationRecipient;
}
