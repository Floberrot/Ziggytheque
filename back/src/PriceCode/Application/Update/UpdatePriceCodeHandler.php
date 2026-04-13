<?php

declare(strict_types=1);

namespace App\PriceCode\Application\Update;

use App\PriceCode\Domain\Exception\PriceCodeNotFoundException;
use App\PriceCode\Domain\PriceCodeRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdatePriceCodeHandler
{
    public function __construct(private PriceCodeRepositoryInterface $repository)
    {
    }

    public function __invoke(UpdatePriceCodeCommand $command): void
    {
        $priceCode = $this->repository->findByCode($command->code);

        if ($priceCode === null) {
            throw new PriceCodeNotFoundException($command->code);
        }

        $priceCode->update($command->label, $command->value);
        $this->repository->save($priceCode);
    }
}
