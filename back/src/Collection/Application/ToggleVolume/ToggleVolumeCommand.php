<?php

declare(strict_types=1);

namespace App\Collection\Application\ToggleVolume;

use App\Collection\Domain\VolumeToggleFieldEnum;

final readonly class ToggleVolumeCommand
{
    public function __construct(
        public string $collectionEntryId,
        public string $volumeEntryId,
        public VolumeToggleFieldEnum $field,
    ) {
    }
}
