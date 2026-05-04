<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Http;

use App\Collection\Application\GetShelf\GetShelfQuery;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/shelf')]
final readonly class ShelfController
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    #[Route('', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetShelfQuery()));
    }
}
