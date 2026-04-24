<?php

declare(strict_types=1);

namespace App\Notification\Application\GetArticles;

use App\Notification\Domain\ArticleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetArticlesHandler
{
    public function __construct(private ArticleRepositoryInterface $repository)
    {
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int, page: int, limit: int, totalPages: int} */
    public function __invoke(GetArticlesQuery $query): array
    {
        $result = $this->repository->findPaginated($query->page, $query->limit, $query->collectionEntryId);

        return [
            'items'      => array_map(static fn ($a) => $a->toArray(), $result['items']),
            'total'      => $result['total'],
            'page'       => $query->page,
            'limit'      => $query->limit,
            'totalPages' => (int) ceil($result['total'] / $query->limit),
        ];
    }
}
