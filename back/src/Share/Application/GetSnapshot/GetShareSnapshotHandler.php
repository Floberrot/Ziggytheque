<?php

declare(strict_types=1);

namespace App\Share\Application\GetSnapshot;

use App\Shared\Domain\Exception\NotFoundException;
use App\Share\Domain\ShareSnapshotRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetShareSnapshotHandler
{
    public function __construct(private ShareSnapshotRepositoryInterface $repository)
    {
    }

    /** @return array<string, mixed> */
    public function __invoke(GetShareSnapshotQuery $query): array
    {
        $snapshot = $this->repository->findByToken($query->token);

        if ($snapshot === null) {
            throw new NotFoundException('Share', $query->token);
        }

        return $snapshot->toPublicArray();
    }
}
