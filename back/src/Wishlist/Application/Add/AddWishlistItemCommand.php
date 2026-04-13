<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Add;

final readonly class AddWishlistItemCommand
{
    public function __construct(public string $mangaId)
    {
    }
}
