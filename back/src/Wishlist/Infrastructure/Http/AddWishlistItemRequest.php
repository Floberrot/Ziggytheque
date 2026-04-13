<?php

declare(strict_types=1);

namespace App\Wishlist\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddWishlistItemRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $mangaId,
    ) {
    }
}
