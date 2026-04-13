<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Get;

use App\Wishlist\Domain\WishlistRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetWishlistHandler
{
    public function __construct(private WishlistRepositoryInterface $repository)
    {
    }

    public function __invoke(GetWishlistQuery $query): array
    {
        return array_map(
            static fn ($item) => $item->toArray(),
            $this->repository->findAll(),
        );
    }
}
