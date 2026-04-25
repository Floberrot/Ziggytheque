<?php

declare(strict_types=1);

namespace App\Wishlist\Shared\Event;

use App\Shared\Domain\Event\FailedEventInterface;

final readonly class RemoveWishlistItemFailedEvent implements FailedEventInterface
{
    public function __construct(
        public string $correlationId,
        public string $wishlistItemId,
        public string $error,
        public string $exceptionClass,
    ) {
    }
}
