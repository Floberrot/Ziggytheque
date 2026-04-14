<?php

declare(strict_types=1);

namespace App\Collection\Application\SyncVolumes;

final readonly class SyncVolumesCommand
{
    public function __construct(
        public string $collectionEntryId,
        /** Extend up to this volume number, creating missing placeholders */
        public ?int $upToVolume = null,
    ) {
    }
}
