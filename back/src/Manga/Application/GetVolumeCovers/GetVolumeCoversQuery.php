<?php

declare(strict_types=1);

namespace App\Manga\Application\GetVolumeCovers;

final readonly class GetVolumeCoversQuery
{
    public function __construct(public string $externalId)
    {
    }
}
