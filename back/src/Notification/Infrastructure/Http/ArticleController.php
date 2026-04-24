<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Http;

use App\Notification\Application\GetActivityLogs\GetActivityLogsQuery;
use App\Notification\Application\GetArticles\GetArticlesQuery;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/articles')]
final readonly class ArticleController
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page              = max(1, (int) $request->query->get('page', 1));
        $limit             = min(50, max(1, (int) $request->query->get('limit', 12)));
        $collectionEntryId = $request->query->get('collectionEntryId') ?: null;

        return new JsonResponse(
            $this->queryBus->ask(new GetArticlesQuery($page, $limit, $collectionEntryId)),
        );
    }

    #[Route('/activity-logs', methods: ['GET'])]
    public function activityLogs(Request $request): JsonResponse
    {
        $page              = max(1, (int) $request->query->get('page', 1));
        $limit             = min(100, max(1, (int) $request->query->get('limit', 50)));
        $eventType         = $request->query->get('eventType') ?: null;
        $status            = $request->query->get('status') ?: null;
        $collectionEntryId = $request->query->get('collectionEntryId') ?: null;

        return new JsonResponse(
            $this->queryBus->ask(new GetActivityLogsQuery($page, $limit, $eventType, $status, $collectionEntryId)),
        );
    }
}
