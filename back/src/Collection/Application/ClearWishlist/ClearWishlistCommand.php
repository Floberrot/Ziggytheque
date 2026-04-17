<?php

declare(strict_types=1);

namespace App\Collection\Application\ClearWishlist;

final readonly class ClearWishlistCommand
{
    public function __construct(public string $collectionEntryId)
    {
    }
}
