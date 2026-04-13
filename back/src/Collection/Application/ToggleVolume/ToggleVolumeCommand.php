<?php

declare(strict_types=1);

namespace App\Collection\Application\ToggleVolume;

final readonly class ToggleVolumeCommand
{
    public function __construct(
        public string $collectionEntryId,
        public string $volumeEntryId,
        public string $field, // 'isOwned' | 'isRead'
    ) {
    }
}
