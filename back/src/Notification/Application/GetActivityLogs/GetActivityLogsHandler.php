<?php

declare(strict_types=1);

namespace App\Notification\Application\GetActivityLogs;

use App\Notification\Domain\ActivityLogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetActivityLogsHandler
{
    public function __construct(private ActivityLogRepositoryInterface $repository) {}

    /** @return array<int, array<string, mixed>> */
    public function __invoke(GetActivityLogsQuery $query): array
    {
        return array_map(
            static fn ($l) => $l->toArray(),
            $this->repository->findRecent($query->limit),
        );
    }
}
