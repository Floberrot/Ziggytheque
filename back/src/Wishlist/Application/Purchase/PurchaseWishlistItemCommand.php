<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Purchase;

final readonly class PurchaseWishlistItemCommand
{
    public function __construct(public string $id)
    {
    }
}
