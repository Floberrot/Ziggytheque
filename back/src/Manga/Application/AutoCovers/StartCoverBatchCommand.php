<?php

declare(strict_types=1);

namespace App\Manga\Application\AutoCovers;

final readonly class StartCoverBatchCommand
{
    /** @param string[]|null $volumeIds */
    public function __construct(
        public string $mangaId,
        public bool $force,
        public ?array $volumeIds,
    ) {
    }
}
