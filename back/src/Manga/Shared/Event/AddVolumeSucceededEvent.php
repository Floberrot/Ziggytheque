<?php

declare(strict_types=1);

namespace App\Manga\Shared\Event;

use App\Shared\Domain\Event\SucceededEventInterface;

final readonly class AddVolumeSucceededEvent implements SucceededEventInterface
{
    public function __construct(
        public string $correlationId,
        public string $mangaId,
        public string $volumeId,
        public int $number,
    ) {
    }
}
