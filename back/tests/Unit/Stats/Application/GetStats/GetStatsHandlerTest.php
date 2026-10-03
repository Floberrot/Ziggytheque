<?php

declare(strict_types=1);

namespace App\Tests\Unit\Stats\Application\GetStats;

use App\Stats\Application\GetStats\GetStatsHandler;
use App\Stats\Application\GetStats\GetStatsQuery;
use App\Stats\Domain\StatsRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class GetStatsHandlerTest extends TestCase
{
    public function testReturnsTheStatsOfTheRepository(): void
    {
        $stats      = ['totalMangas' => 4, 'totalOwned' => 30];
        $repository = $this->createMock(StatsRepositoryInterface::class);
        $repository->expects($this->once())->method('getStats')->willReturn($stats);

        $this->assertSame($stats, (new GetStatsHandler($repository))(new GetStatsQuery()));
    }
}
