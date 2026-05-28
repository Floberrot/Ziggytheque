<?php

declare(strict_types=1);

namespace App\Notification\Application\Test;

final readonly class SendTestNotificationMessage
{
    public function __construct(public string $userId)
    {
    }
}
