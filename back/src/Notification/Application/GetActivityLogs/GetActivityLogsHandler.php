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

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, limit: int} */
    public function __invoke(GetActivityLogsQuery $query): array
    {
        $filters = array_filter([
            'eventType'         => $query->eventType,
            'status'            => $query->status,
            'collectionEntryId' => $query->collectionEntryId,
            'ownerId'           => $query->ownerId,
            'from'              => $query->from,
            'to'                => $query->to,
            'search'            => $query->search,
        ]);

        $result = $this->repository->findPaginated($query->page, $query->limit, $filters);

        return (new ActivityLogPaginatedResult(
            items: $result['items'],
            total: $result['total'],
            page:  $query->page,
            limit: $query->limit,
        ))->toArray();
    }
}
