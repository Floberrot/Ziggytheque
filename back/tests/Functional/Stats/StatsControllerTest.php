<?php

declare(strict_types=1);
namespace App\Tests\Functional\Stats;
use App\Tests\Functional\BaseFunctionalTest;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Stats\Application\GetStats\GetStatsQuery;
class StatsControllerTest extends BaseFunctionalTest
{
    public function testGetStatsReturns200WithExpectedShape(): void
    {
        $client = $this->createAuthenticatedClient();
        $stats = [
            'totalMangas' => 5,
            'totalOwned' => 20,
            'totalRead' => 15,
            'totalWishlist' => 3,
            'collectionValue' => 139.95,
            'genreBreakdown' => ['action' => 3, 'shonen' => 2],
            'recentAdditions' => [],
        ];
        $mockQuery = $this->createMock(QueryBusInterface::class);
        $mockQuery->method('ask')
            ->with($this->isInstanceOf(GetStatsQuery::class))
            ->willReturn($stats);
        static::getContainer()->set(QueryBusInterface::class, $mockQuery);
        $client->request('GET', '/api/stats');
        $this->assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertSame(5, $body['totalMangas']);
        $this->assertSame(20, $body['totalOwned']);
        $this->assertArrayHasKey('genreBreakdown', $body);
    }
}
