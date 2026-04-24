<?php

declare(strict_types=1);

namespace App\Notification\Application\GetActivityLogs;

use App\Notification\Domain\ActivityLogRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetActivityLogsHandler
{
    public function __construct(private ActivityLogRepositoryInterface $repository)
    {
    }

    /** @return array<string, mixed> */
    public function __invoke(GetActivityLogsQuery $query): array
    {
        $filters = array_filter([
            'eventType'         => $query->eventType,
            'status'            => $query->status,
            'collectionEntryId' => $query->collectionEntryId,
        ]);

        $result = $this->repository->findPaginated($query->page, $query->limit, $filters);

        return [
            'items'      => array_map(static fn ($l) => $l->toArray(), $result['items']),
            'total'      => $result['total'],
            'page'       => $query->page,
            'limit'      => $query->limit,
            'totalPages' => (int) ceil($result['total'] / $query->limit),
        ];
    }
}
