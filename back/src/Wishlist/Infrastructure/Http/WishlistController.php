<?php

declare(strict_types=1);

namespace App\Wishlist\Infrastructure\Http;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Wishlist\Application\Add\AddWishlistItemCommand;
use App\Wishlist\Application\Get\GetWishlistQuery;
use App\Wishlist\Application\Purchase\PurchaseWishlistItemCommand;
use App\Wishlist\Application\Remove\RemoveWishlistItemCommand;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/wishlist')]
final readonly class WishlistController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetWishlistQuery()));
    }

    #[Route('', methods: ['POST'])]
    public function add(#[MapRequestPayload] AddWishlistItemRequest $request): JsonResponse
    {
        $id = $this->commandBus->dispatch(new AddWishlistItemCommand($request->mangaId));

        return new JsonResponse(['id' => $id], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function remove(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new RemoveWishlistItemCommand($id));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/purchase', methods: ['POST'])]
    public function purchase(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new PurchaseWishlistItemCommand($id));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
