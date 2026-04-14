<?php

declare(strict_types=1);

namespace App\Collection\Application\WishlistRemaining;

final readonly class WishlistRemainingCommand
{
    public function __construct(public string $collectionEntryId)
    {
    }
}
