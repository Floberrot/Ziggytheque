<?php

declare(strict_types=1);

namespace App\PriceCode\Application\Create;

use App\PriceCode\Domain\Exception\PriceCodeAlreadyExistsException;
use App\PriceCode\Domain\PriceCode;
use App\PriceCode\Domain\PriceCodeRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreatePriceCodeHandler
{
    public function __construct(private PriceCodeRepositoryInterface $repository)
    {
    }

    public function __invoke(CreatePriceCodeCommand $command): void
    {
        if ($this->repository->findByCode($command->code) !== null) {
            throw new PriceCodeAlreadyExistsException($command->code);
        }

        $this->repository->save(new PriceCode($command->code, $command->label, $command->value));
    }
}
