<?php

declare(strict_types=1);

namespace App\Notification\Application\Email;

final readonly class SendFollowingNotificationMessage
{
    public function __construct(
        public string $collectionEntryId,
        /** @var string[] */
        public array $articleIds,
    ) {}
}
