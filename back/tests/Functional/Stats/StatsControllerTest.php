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

        // Extended stats
        $this->assertArrayHasKey('readingStatusBreakdown', $data);
        $this->assertArrayHasKey('topAuthors', $data);
        $this->assertArrayHasKey('averageRating', $data);
        $this->assertArrayHasKey('ratedCount', $data);
        $this->assertArrayHasKey('monthlyAdditions', $data);

        $this->assertIsArray($data['topAuthors']);
        $this->assertIsArray($data['monthlyAdditions']);
        // The trailing 12-month window is always returned, zero-filled.
        $this->assertCount(12, $data['monthlyAdditions']);
        $this->assertArrayHasKey('month', $data['monthlyAdditions'][0]);
        $this->assertArrayHasKey('count', $data['monthlyAdditions'][0]);
    }

    public function testExtendedStatsReflectCollectionContent(): void
    {
        // Create a series, add it, mark it completed, rate it.
        $mangaResponse = $this->jsonRequest('POST', '/api/manga', [
            'title'        => 'Berserk',
            'language'     => 'fr',
            'totalVolumes' => 2,
            'author'       => 'Kentaro Miura',
        ]);
        $mangaId = (string) json_decode((string) $mangaResponse->getContent(), true)['id'];

        $entryId = (string) json_decode(
            (string) $this->jsonRequest('POST', '/api/collection', ['mangaId' => $mangaId])->getContent(),
            true,
        )['id'];

        $this->jsonRequest('PATCH', '/api/collection/' . $entryId . '/status', ['status' => 'completed']);
        $this->jsonRequest('PATCH', '/api/collection/' . $entryId . '/rating', ['rating' => 9]);

        $data = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/stats'));

        $this->assertSame(1, $data['ratedCount']);
        $this->assertEqualsWithDelta(9.0, $data['averageRating'], 0.001);
        $this->assertSame(1, $data['readingStatusBreakdown']['completed'] ?? null);
        $this->assertContains(
            ['author' => 'Kentaro Miura', 'count' => 1],
            $data['topAuthors'],
        );
    }

    public function testGetStatsRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/stats', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }
}
