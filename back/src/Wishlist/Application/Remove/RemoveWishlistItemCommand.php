<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Remove;

final readonly class RemoveWishlistItemCommand
{
    public function __construct(public string $id)
    {
    }
}
