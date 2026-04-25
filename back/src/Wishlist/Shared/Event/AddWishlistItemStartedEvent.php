<?php

declare(strict_types=1);

namespace App\Wishlist\Shared\Event;

use Symfony\Component\Uid\Uuid;
use App\Shared\Domain\Event\StartedEventInterface;

final readonly class AddWishlistItemStartedEvent implements StartedEventInterface
{
    public string $correlationId;

    public function __construct(
        public string $mangaId,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
