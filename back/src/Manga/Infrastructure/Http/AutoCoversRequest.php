<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

final readonly class AutoCoversRequest
{
    /**
     * @param string[]|null $volumeIds Restrict to specific volume IDs; null means all volumes.
     */
    public function __construct(
        public bool $force = false,
        public ?array $volumeIds = null,
    ) {
    }
}
