<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Get;

use App\Collection\Domain\CollectionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetWishlistHandler
{
    public function __construct(private CollectionRepositoryInterface $collectionRepository)
    {
    }

    public function __invoke(GetWishlistQuery $query): array
    {
        return array_map(
            static fn ($entry) => $entry->toArray(),
            $this->collectionRepository->findWithWishlistVolumes(),
        );
    }
}
