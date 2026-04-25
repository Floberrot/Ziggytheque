<?php

declare(strict_types=1);

namespace App\Wishlist\Shared\Event;

use App\Shared\Domain\Event\FailedEventInterface;

final readonly class AddWishlistItemFailedEvent implements FailedEventInterface
{
    public function __construct(
        public string $correlationId,
        public string $mangaId,
        public string $error,
        public string $exceptionClass,
    ) {
    }
}
