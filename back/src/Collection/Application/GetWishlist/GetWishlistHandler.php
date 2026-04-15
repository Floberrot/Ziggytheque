<?php

declare(strict_types=1);

namespace App\Collection\Application\GetWishlist;

use App\Collection\Domain\CollectionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetWishlistHandler
{
    public function __construct(private CollectionRepositoryInterface $repository)
    {
    }

    public function __invoke(GetWishlistQuery $query): array
    {
        return array_map(
            static fn ($entry) => $entry->toDetailArray(),
            $this->repository->findWithWishedVolumes(),
        );
    }
}
