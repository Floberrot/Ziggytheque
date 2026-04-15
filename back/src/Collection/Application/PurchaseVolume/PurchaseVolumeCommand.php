<?php

declare(strict_types=1);

namespace App\Collection\Application\PurchaseVolume;

final readonly class PurchaseVolumeCommand
{
    public function __construct(
        public string $collectionEntryId,
        public string $volumeEntryId,
    ) {
    }
}
