<?php

declare(strict_types=1);

namespace App\Manga\Application\Scan;

/**
 * A phone-as-scanner session. With a manga + volume it targets that tome (cover /
 * ISBN enrichment); without them it is a free session — every ISBN scanned on the
 * phone lands on the desktop "add a manga" page.
 */
final readonly class CreateScanSessionCommand
{
    public function __construct(
        public ?string $mangaId = null,
        public ?string $volumeId = null,
    ) {
    }
}
