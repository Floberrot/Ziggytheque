<?php

declare(strict_types=1);

namespace App\Wishlist\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class WishlistFilterRequest
{
    public ?string $search = null;

    #[Assert\Positive]
    public int $page = 1;
}
