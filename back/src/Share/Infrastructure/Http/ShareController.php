<?php

declare(strict_types=1);

namespace App\Share\Infrastructure\Http;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Share\Application\CreateSnapshot\CreateShareSnapshotCommand;
use App\Share\Application\GetSnapshot\GetShareSnapshotQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/share')]
final readonly class ShareController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private string $frontUrl,
    ) {
    }

    /** Freezes the current user's public stats into a permanent share link. */
    #[Route('', methods: ['POST'])]
    public function create(): JsonResponse
    {
        $token = $this->commandBus->dispatch(new CreateShareSnapshotCommand());

        return new JsonResponse([
            'token' => $token,
            'url'   => rtrim($this->frontUrl, '/') . '/share/' . $token,
        ], Response::HTTP_CREATED);
    }

    /** Public, no-auth read of a frozen snapshot. */
    #[Route('/{token}', methods: ['GET'], requirements: ['token' => '[a-f0-9]{32}'])]
    public function get(string $token): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetShareSnapshotQuery($token)));
    }
}
