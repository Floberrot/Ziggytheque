<?php

declare(strict_types=1);

namespace App\Manga\Shared\Event;

use App\Shared\Domain\Event\FailedEventInterface;

final readonly class AddVolumeFailedEvent implements FailedEventInterface
{
    public function __construct(
        public string $correlationId,
        public string $mangaId,
        public int $number,
        public string $error,
        public string $exceptionClass,
    ) {
    }
}
