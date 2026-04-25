<?php

declare(strict_types=1);

namespace App\Collection\Shared\Event;

use App\Shared\Domain\Event\FailedEventInterface;

final readonly class AddToCollectionFailedEvent implements FailedEventInterface
{
    public function __construct(
        public string $correlationId,
        public string $mangaId,
        public string $mangaTitle,
        public string $error,
        public string $exceptionClass,
    ) {
    }
}
