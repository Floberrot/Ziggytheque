<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http;

use App\Auth\Application\Gate\GateCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final readonly class GateController
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    #[Route('/gate', methods: ['POST'])]
    public function gate(#[MapRequestPayload] GateRequest $request): JsonResponse
    {
        $token = $this->commandBus->dispatch(new GateCommand($request->password));

        return new JsonResponse(['token' => $token]);
    }
}
