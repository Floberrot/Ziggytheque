<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Http;

use App\Collection\Application\Add\AddToCollectionCommand;
use App\Collection\Application\Get\GetCollectionQuery;
use App\Collection\Application\GetDetail\GetCollectionDetailQuery;
use App\Collection\Application\Remove\RemoveFromCollectionCommand;
use App\Collection\Application\ToggleVolume\ToggleVolumeCommand;
use App\Collection\Application\UpdateStatus\UpdateReadingStatusCommand;
use App\Collection\Application\WishlistRemaining\WishlistRemainingCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/collection')]
final readonly class CollectionController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetCollectionQuery()));
    }

    #[Route('', methods: ['POST'])]
    public function add(#[MapRequestPayload] AddToCollectionRequest $request): JsonResponse
    {
        $id = $this->commandBus->dispatch(new AddToCollectionCommand($request->mangaId));

        return new JsonResponse(['id' => $id], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetCollectionDetailQuery($id)));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function remove(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new RemoveFromCollectionCommand($id));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/status', methods: ['PATCH'])]
    public function updateStatus(string $id, #[MapRequestPayload] UpdateStatusRequest $request): JsonResponse
    {
        $this->commandBus->dispatch(new UpdateReadingStatusCommand($id, $request->status));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/volumes/{volumeEntryId}/toggle', methods: ['PATCH'])]
    public function toggleVolume(
        string $id,
        string $volumeEntryId,
        #[MapRequestPayload] ToggleVolumeRequest $request,
    ): JsonResponse {
        $this->commandBus->dispatch(new ToggleVolumeCommand($id, $volumeEntryId, $request->field));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/wishlist-remaining', methods: ['POST'])]
    public function wishlistRemaining(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new WishlistRemainingCommand($id));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
