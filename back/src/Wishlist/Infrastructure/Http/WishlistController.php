<?php

declare(strict_types=1);

namespace App\Wishlist\Infrastructure\Http;

use App\Collection\Application\AddRemainingToWishlist\AddRemainingToWishlistCommand;
use App\Collection\Application\ClearWishlist\ClearWishlistCommand;
use App\Collection\Application\GetWishlist\GetWishlistQuery;
use App\Collection\Application\PurchaseVolume\PurchaseVolumeCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/wishlist')]
final readonly class WishlistController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
    ) {
    }

    /** Returns all collection entries that have at least one wished (non-owned) volume */
    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetWishlistQuery()));
    }

    /** Add all non-owned volumes of a collection entry to the wishlist */
    #[Route('/{id}/add-remaining', methods: ['POST'])]
    public function addRemaining(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new AddRemainingToWishlistCommand($id));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** Remove all wished flags for a collection entry (clear wishlist for this oeuvre) */
    #[Route('/{id}', methods: ['DELETE'])]
    public function clear(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new ClearWishlistCommand($id));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** Mark a specific volume as purchased (owned=true, wished=false) */
    #[Route('/{id}/volumes/{volumeEntryId}/purchase', methods: ['POST'])]
    public function purchase(string $id, string $volumeEntryId): JsonResponse
    {
        $this->commandBus->dispatch(new PurchaseVolumeCommand($id, $volumeEntryId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
