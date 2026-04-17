<?php

declare(strict_types=1);

namespace App\PriceCode\Application\List;

use App\PriceCode\Domain\PriceCodeRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListPriceCodesHandler
{
    public function __construct(private PriceCodeRepositoryInterface $repository)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function __invoke(ListPriceCodesQuery $query): array
    {
        return array_map(
            static fn ($pc) => $pc->toArray(),
            $this->repository->findAll(),
        );
    }
}
