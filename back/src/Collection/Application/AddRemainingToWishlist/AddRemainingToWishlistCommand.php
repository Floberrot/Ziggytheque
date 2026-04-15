<?php

declare(strict_types=1);

namespace App\Collection\Application\AddRemainingToWishlist;

final readonly class AddRemainingToWishlistCommand
{
    public function __construct(public string $collectionEntryId)
    {
    }
}
