<?php

declare(strict_types=1);

namespace App\Tests\Functional\Collection;

use App\Tests\Functional\AbstractApiTestCase;
use App\Tests\Functional\Fixtures\UserFixtureFactory;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * One account never reaches another one's collection: every route that reads or
 * changes a series of the collection answers 404 to another account, and leaves the
 * owner's data untouched. Guards the Doctrine owner filters (collection_owner).
 */
final class OwnerIsolationTest extends AbstractApiTestCase
{
    private string $entryId;
    private string $volumeEntryId;

    /** @var array<string, string> */
    private array $intruderHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        // The setUp admin owns a 3-tome series with tome 1 owned and the rest wished.
        $manga = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/manga', [
            'title' => 'Private Series', 'language' => 'fr', 'totalVolumes' => 3,
        ]));
        $entry = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/collection', ['mangaId' => $manga['id']]));
        $this->entryId = (string) $entry['id'];

        $detail = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/collection/' . $this->entryId));
        $this->volumeEntryId = (string) $detail['volumes'][0]['id'];
        $this->jsonRequest('PATCH', '/api/collection/' . $this->entryId . '/volumes/' . $this->volumeEntryId . '/toggle', ['field' => 'isOwned']);
        $this->jsonRequest('POST', '/api/collection/' . $this->entryId . '/add-to-wishlist');

        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'intruder@test.local');
        $this->intruderHeaders = [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenForUser('intruder@test.local'),
            'HTTP_ACCEPT'        => 'application/json',
            'CONTENT_TYPE'       => 'application/json',
        ];
    }

    /** @return iterable<string, array{string, string, array<string, mixed>}> */
    public static function ownerRoutes(): iterable
    {
        yield 'read the detail' => ['GET', '/api/collection/{entry}', []];
        yield 'change the reading status' => ['PATCH', '/api/collection/{entry}/status', ['status' => 'completed']];
        yield 'rate' => ['PATCH', '/api/collection/{entry}/rating', ['rating' => 2]];
        yield 'follow' => ['PATCH', '/api/collection/{entry}/follow', []];
        yield 'toggle a tome' => ['PATCH', '/api/collection/{entry}/volumes/{volume}/toggle', ['field' => 'isRead']];
        yield 'set every price' => ['PATCH', '/api/collection/{entry}/batch-price', ['price' => 99]];
        yield 'sync the tomes' => ['POST', '/api/collection/{entry}/sync-volumes', ['upToVolume' => 5]];
        yield 'wish the missing tomes' => ['POST', '/api/collection/{entry}/add-to-wishlist', []];
        yield 'buy a tome' => ['POST', '/api/wishlist/{entry}/volumes/{volume}/purchase', []];
        yield 'clear the wishlist' => ['DELETE', '/api/wishlist/{entry}', []];
        yield 'remove the series' => ['DELETE', '/api/collection/{entry}', []];
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('ownerRoutes')]
    public function testAnotherAccountCannotReachTheSeries(string $method, string $uri, array $body): void
    {
        $path = strtr($uri, ['{entry}' => $this->entryId, '{volume}' => $this->volumeEntryId]);

        $this->client->request($method, $path, [], [], $this->intruderHeaders, $body !== [] ? (string) json_encode($body) : '');

        $this->assertSame(404, $this->client->getResponse()->getStatusCode(), $method . ' ' . $uri);

        // The owner's series is exactly as before.
        $detail = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/collection/' . $this->entryId));
        $this->assertCount(3, $detail['volumes']);
        $this->assertNull($detail['rating']);
        $this->assertFalse($detail['notificationsEnabled']);
        $this->assertSame([true, false, false], array_column($detail['volumes'], 'isOwned'));
        $this->assertSame([false, false, false], array_column($detail['volumes'], 'isRead'));
    }

    public function testAnotherAccountSeesNeitherTheSeriesNorItsNumbers(): void
    {
        $this->client->request('GET', '/api/wishlist', [], [], $this->intruderHeaders);
        $wishlist = $this->assertJsonStatus(200, $this->client->getResponse());
        $this->assertSame(0, $wishlist['total']);

        $this->client->request('GET', '/api/stats', [], [], $this->intruderHeaders);
        $stats = $this->assertJsonStatus(200, $this->client->getResponse());
        $this->assertSame(0, $stats['totalMangas']);
        $this->assertSame(0, $stats['totalOwned']);
        $this->assertSame(0, $stats['totalWishlist']);
        $this->assertSame([], $stats['recentAdditions']);

        // The owner's own numbers are there.
        $ownerStats = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/stats'));
        $this->assertSame(1, $ownerStats['totalMangas']);
        $this->assertSame(1, $ownerStats['totalOwned']);
    }
}
