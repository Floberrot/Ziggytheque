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

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, limit: int} */
    public function __invoke(GetWishlistQuery $query): array
    {
        $result = $this->repository->findWishedFiltered($query);

        return (new WishlistPaginatedResult(
            items: $result['items'],
            total: $result['total'],
            page:  $query->page,
            limit: $query->limit,
        ))->toArray();
    }
}
