<?php

declare(strict_types=1);

namespace App\Collection\Application\Get;

use App\Collection\Domain\CollectionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetCollectionHandler
{
    public function __construct(private CollectionRepositoryInterface $repository)
    {
    }

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, limit: int} */
    public function __invoke(GetCollectionQuery $query): array
    {
        $result = $this->repository->findFiltered($query);

        return (new CollectionPaginatedResult(
            items: $result['items'],
            total: $result['total'],
            page:  $query->page,
            limit: $query->limit,
        ))->toArray();
    }
}
