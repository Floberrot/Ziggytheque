<?php

declare(strict_types=1);

namespace App\Stats\Infrastructure\Http;

use App\Shared\Application\Bus\QueryBusInterface;
use App\Stats\Application\GetStats\GetStatsQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/stats')]
final readonly class StatsController
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    #[Route('', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetStatsQuery()));
    }
}
