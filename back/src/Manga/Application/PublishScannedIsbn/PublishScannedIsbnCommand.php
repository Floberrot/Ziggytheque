<?php

declare(strict_types=1);

namespace App\Manga\Application\PublishScannedIsbn;

final readonly class PublishScannedIsbnCommand
{
    public function __construct(
        public string $sessionId,
        public string $isbn,
    ) {
    }
}
