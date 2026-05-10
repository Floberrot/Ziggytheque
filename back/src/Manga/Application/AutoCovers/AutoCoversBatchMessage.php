<?php

declare(strict_types=1);

namespace App\Manga\Application\AutoCovers;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: 'async')]
final readonly class AutoCoversBatchMessage
{
    /** @param string[]|null $volumeIds */
    public function __construct(
        public string $mangaId,
        public string $batchId,
        public bool $force,
        public ?array $volumeIds,
    ) {
    }
}
