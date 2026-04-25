<?php

declare(strict_types=1);

namespace App\Stats\Application\GetStats;

use App\Stats\Domain\StatsRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetStatsHandler
{
    public function __construct(private StatsRepositoryInterface $repository)
    {
    }

    /** @return array<string, mixed> */
    public function __invoke(GetStatsQuery $query): array
    {
        return $this->repository->getStats();
    }
}
