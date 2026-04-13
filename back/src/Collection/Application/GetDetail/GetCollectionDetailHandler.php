<?php

declare(strict_types=1);

namespace App\Collection\Application\GetDetail;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetCollectionDetailHandler
{
    public function __construct(private CollectionRepositoryInterface $repository)
    {
    }

    public function __invoke(GetCollectionDetailQuery $query): array
    {
        $entry = $this->repository->findById($query->id);

        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $query->id);
        }

        return $entry->toDetailArray();
    }
}
