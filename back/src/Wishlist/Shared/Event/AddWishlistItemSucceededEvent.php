<?php

declare(strict_types=1);

namespace App\Wishlist\Shared\Event;

use App\Shared\Domain\Event\SucceededEventInterface;

final readonly class AddWishlistItemSucceededEvent implements SucceededEventInterface
{
    public function __construct(
        public string $correlationId,
        public string $wishlistItemId,
        public string $mangaId,
    ) {
    }
}
