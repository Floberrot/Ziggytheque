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

    public function __invoke(GetCollectionQuery $query): array
    {
        return array_map(
            static fn ($entry) => $entry->toArray(),
            $this->repository->findAll(),
        );
    }
}
