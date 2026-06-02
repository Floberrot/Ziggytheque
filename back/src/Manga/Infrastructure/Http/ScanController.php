<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use App\Manga\Application\Scan\CreateScanSessionCommand;
use App\Manga\Application\Scan\SubmitScanCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/scan')]
final readonly class ScanController
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    #[Route('/sessions', methods: ['POST'])]
    public function createSession(#[MapRequestPayload] CreateScanSessionRequest $request): JsonResponse
    {
        $result = $this->commandBus->dispatch(new CreateScanSessionCommand(
            mangaId: $request->mangaId,
            volumeId: $request->volumeId,
        ));

        return new JsonResponse($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/submit', methods: ['POST'])]
    public function submit(#[MapRequestPayload] SubmitScanRequest $request): JsonResponse
    {
        $this->commandBus->dispatch(new SubmitScanCommand(
            scanToken: $request->scanToken,
            isbn: $request->isbn,
        ));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
