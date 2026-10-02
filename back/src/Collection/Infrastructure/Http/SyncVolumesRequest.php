<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Http;

use App\Manga\Domain\Manga;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SyncVolumesRequest
{
    public function __construct(
        /** Last tome the series should have; null only tracks the tomes already known. */
        #[Assert\Range(min: 1, max: Manga::MAX_VOLUMES)]
        public ?int $upToVolume = null,
    ) {
    }
}
