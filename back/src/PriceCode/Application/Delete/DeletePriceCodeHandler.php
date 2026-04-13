<?php

declare(strict_types=1);

namespace App\PriceCode\Application\Delete;

use App\PriceCode\Domain\Exception\PriceCodeNotFoundException;
use App\PriceCode\Domain\PriceCodeRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeletePriceCodeHandler
{
    public function __construct(private PriceCodeRepositoryInterface $repository)
    {
    }

    public function __invoke(DeletePriceCodeCommand $command): void
    {
        $priceCode = $this->repository->findByCode($command->code);

        if ($priceCode === null) {
            throw new PriceCodeNotFoundException($command->code);
        }

        $this->repository->delete($priceCode);
    }
}
