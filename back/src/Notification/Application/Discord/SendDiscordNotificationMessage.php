<?php

declare(strict_types=1);

namespace App\Notification\Application\Discord;

final readonly class SendDiscordNotificationMessage
{
    public function __construct(
        public string $collectionEntryId,
        /** @var string[] */
        public array $articleIds,
    ) {
    }
}
