<?php

declare(strict_types=1);

namespace App\Tests\Functional\Collection;

use App\Tests\Doubles\Doctrine\QueryCounter;
use App\Tests\Functional\AbstractApiTestCase;

/**
 * Reading a page costs a fixed number of queries, whatever the number of series
 * and tomes — no N+1 (one query per entry or per tome).
 */
final class CollectionQueryCountTest extends AbstractApiTestCase
{
    private const int SERIES = 4;
    private const int TOMES_PER_SERIES = 30;

    /** Auth, activity log and the page's own reads: far below one query per tome. */
    private const int MAX_QUERIES_PER_PAGE = 8;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
    }

    /** @return list<string> collection entry ids */
    private function seedCollection(): array
    {
        $entryIds = [];
        for ($series = 1; $series <= self::SERIES; $series++) {
            $manga = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/manga', [
                'title'        => 'Série ' . $series,
                'language'     => 'fr',
                'totalVolumes' => self::TOMES_PER_SERIES,
            ]));
            $entry = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/collection', ['mangaId' => $manga['id']]));
            $entryIds[] = (string) $entry['id'];
            // Every series has missing tomes on the wishlist.
            $this->jsonRequest('POST', '/api/collection/' . $entry['id'] . '/add-to-wishlist');
        }

        return $entryIds;
    }

    private function queriesFor(string $uri): int
    {
        $counter = static::getContainer()->get(QueryCounter::class);
        $this->assertInstanceOf(QueryCounter::class, $counter);
        $counter->reset();

        $this->assertJsonStatus(200, $this->jsonRequest('GET', $uri));

        return $counter->count();
    }

    public function testASeriesDetailDoesNotCostAQueryPerTome(): void
    {
        $entryIds = $this->seedCollection();

        $queries = $this->queriesFor('/api/collection/' . $entryIds[0]);

        $this->assertLessThanOrEqual(self::MAX_QUERIES_PER_PAGE, $queries, sprintf('%d queries for one series', $queries));
    }

    public function testTheCollectionListDoesNotCostAQueryPerEntry(): void
    {
        $this->seedCollection();

        $queries = $this->queriesFor('/api/collection');

        $this->assertLessThanOrEqual(self::MAX_QUERIES_PER_PAGE, $queries, sprintf('%d queries for the list', $queries));
    }

    public function testTheWishlistDoesNotCostAQueryPerEntry(): void
    {
        $this->seedCollection();

        $queries = $this->queriesFor('/api/wishlist');

        $this->assertLessThanOrEqual(self::MAX_QUERIES_PER_PAGE, $queries, sprintf('%d queries for the wishlist', $queries));
    }
}
