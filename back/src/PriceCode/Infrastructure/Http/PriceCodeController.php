<?php

declare(strict_types=1);

namespace App\PriceCode\Infrastructure\Http;

use App\PriceCode\Application\Create\CreatePriceCodeCommand;
use App\PriceCode\Application\Delete\DeletePriceCodeCommand;
use App\PriceCode\Application\List\ListPriceCodesQuery;
use App\PriceCode\Application\Update\UpdatePriceCodeCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/price-codes')]
final readonly class PriceCodeController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new ListPriceCodesQuery()));
    }

    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreatePriceCodeRequest $request): JsonResponse
    {
        $this->commandBus->dispatch(new CreatePriceCodeCommand(
            $request->code,
            $request->label,
            $request->value,
        ));

        return new JsonResponse(null, Response::HTTP_CREATED);
    }

    #[Route('/{code}', methods: ['PATCH'])]
    public function update(string $code, #[MapRequestPayload] UpdatePriceCodeRequest $request): JsonResponse
    {
        $this->commandBus->dispatch(new UpdatePriceCodeCommand(
            $code,
            $request->label,
            $request->value,
        ));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{code}', methods: ['DELETE'])]
    public function delete(string $code): JsonResponse
    {
        $this->commandBus->dispatch(new DeletePriceCodeCommand($code));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
