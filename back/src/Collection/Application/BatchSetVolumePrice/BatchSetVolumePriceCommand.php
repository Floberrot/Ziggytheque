<?php

declare(strict_types=1);

namespace App\Collection\Application\BatchSetVolumePrice;

final readonly class BatchSetVolumePriceCommand
{
    public function __construct(
        public string $collectionEntryId,
        public float $price,
    ) {
    }
}
