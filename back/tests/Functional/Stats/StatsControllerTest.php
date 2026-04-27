<?php

declare(strict_types=1);

namespace App\Tests\Functional\Stats;

use App\Tests\Functional\AbstractApiTestCase;

final class StatsControllerTest extends AbstractApiTestCase
{
    public function testGetStatsReturns200(): void
    {
        $response = $this->jsonRequest('GET', '/api/stats');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertArrayHasKey('totalMangas', $data);
        $this->assertArrayHasKey('totalOwned', $data);
        $this->assertArrayHasKey('totalRead', $data);
        $this->assertArrayHasKey('totalWishlist', $data);
        $this->assertArrayHasKey('ownedValue', $data);
        $this->assertArrayHasKey('genreBreakdown', $data);
        $this->assertArrayHasKey('recentAdditions', $data);
    }

    public function testGetStatsRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/stats', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }
}
