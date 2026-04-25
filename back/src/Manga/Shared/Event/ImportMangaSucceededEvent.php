<?php

declare(strict_types=1);

namespace App\Manga\Shared\Event;

use App\Shared\Domain\Event\SucceededEventInterface;

final readonly class ImportMangaSucceededEvent implements SucceededEventInterface
{
    public function __construct(
        public string $correlationId,
        public string $mangaId,
        public string $title,
    ) {
    }
}
