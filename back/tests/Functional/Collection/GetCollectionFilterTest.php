<?php

declare(strict_types=1);

namespace App\Tests\Functional\Collection;

use App\Tests\Functional\AbstractApiTestCase;

final class GetCollectionFilterTest extends AbstractApiTestCase
{
    /** Unique prefix for this test method — keeps data isolated from other tests in the suite */
    private string $pfx = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->pfx = 'GCFT_' . bin2hex(random_bytes(4)) . '_';
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function createManga(
        string $title,
        string $genre = 'shonen',
        ?string $edition = null,
        int $volumes = 0,
    ): string {
        $body = ['title' => $title, 'language' => 'fr', 'genre' => $genre];
        if ($edition !== null) {
            $body['edition'] = $edition;
        }
        if ($volumes > 0) {
            $body['totalVolumes'] = $volumes;
        }

        $response = $this->jsonRequest('POST', '/api/manga', $body);
        $data = json_decode((string) $response->getContent(), true);

        return (string) $data['id'];
    }

    private function addToCollection(string $mangaId): string
    {
        $response = $this->jsonRequest('POST', '/api/collection', ['mangaId' => $mangaId]);
        $data     = json_decode((string) $response->getContent(), true);

        return (string) $data['id'];
    }

    private function listCollection(array $params = []): array
    {
        $qs       = $params !== [] ? '?' . http_build_query($params) : '';
        $response = $this->jsonRequest('GET', '/api/collection' . $qs);

        return $this->assertJsonStatus(200, $response);
    }

    // ── Response shape ───────────────────────────────────────────────────────

    public function testNoParamsReturnsPaginatedShape(): void
    {
        $data = $this->listCollection();

        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('limit', $data);
        $this->assertIsArray($data['items']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(20, $data['limit']);
    }

    public function testEmptySearchReturnsZeroShape(): void
    {
        // No data created — unique search term guarantees zero matches
        $data = $this->listCollection(['search' => $this->pfx . '__no_match__']);

        $this->assertSame([], $data['items']);
        $this->assertSame(0, $data['total']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(20, $data['limit']);
    }

    // ── Pagination ───────────────────────────────────────────────────────────

    public function testPageTwoReturnsCorrectOffset(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->addToCollection($this->createManga($this->pfx . "Manga$i"));
        }

        $page1 = $this->listCollection(['search' => $this->pfx, 'page' => 1]);
        $page2 = $this->listCollection(['search' => $this->pfx, 'page' => 2]);

        $this->assertSame(3, $page1['total']);
        $this->assertCount(3, $page1['items']);
        $this->assertCount(0, $page2['items']);
        $this->assertSame(2, $page2['page']);
    }

    // ── Search ───────────────────────────────────────────────────────────────

    public function testSearchFiltersByTitleCaseInsensitive(): void
    {
        $this->addToCollection($this->createManga($this->pfx . 'Naruto'));
        $this->addToCollection($this->createManga($this->pfx . 'One Piece'));

        $data = $this->listCollection(['search' => strtolower($this->pfx . 'naruto')]);

        $this->assertCount(1, $data['items']);
        $this->assertSame(1, $data['total']);
        $this->assertStringContainsString('Naruto', $data['items'][0]['manga']['title']);
    }

    public function testSearchPartialMatch(): void
    {
        $this->addToCollection($this->createManga($this->pfx . 'Naruto Shippuden'));
        $this->addToCollection($this->createManga($this->pfx . 'Bleach'));

        // Unique prefix + 'Naruto' will only match the first entry
        $data = $this->listCollection(['search' => $this->pfx . 'Naru']);

        $this->assertSame(1, $data['total']);
    }

    // ── Genre ────────────────────────────────────────────────────────────────

    public function testGenreFilterReturnsOnlyMatchingEntries(): void
    {
        $this->addToCollection($this->createManga($this->pfx . 'Shonen Manga', 'shonen'));
        $this->addToCollection($this->createManga($this->pfx . 'Seinen Manga', 'seinen'));

        $data = $this->listCollection(['search' => $this->pfx, 'genre' => 'shonen']);

        $this->assertSame(1, $data['total']);
        $this->assertSame('shonen', $data['items'][0]['manga']['genre']);
    }

    // ── Reading status ───────────────────────────────────────────────────────

    public function testReadingStatusFilterReturnsOnlyMatchingEntries(): void
    {
        $entryId = $this->addToCollection($this->createManga($this->pfx . 'Completed Manga'));
        $this->addToCollection($this->createManga($this->pfx . 'Other Manga'));

        $this->jsonRequest('PATCH', '/api/collection/' . $entryId . '/status', ['status' => 'completed']);

        $data = $this->listCollection(['search' => $this->pfx, 'readingStatus' => 'completed']);

        $this->assertSame(1, $data['total']);
        $this->assertSame('completed', $data['items'][0]['readingStatus']);
    }

    // ── Followed ─────────────────────────────────────────────────────────────

    public function testFollowedFilterReturnsOnlyFollowedEntries(): void
    {
        $followedId = $this->addToCollection($this->createManga($this->pfx . 'Followed Manga'));
        $this->addToCollection($this->createManga($this->pfx . 'Unfollowed Manga'));

        $this->jsonRequest('PATCH', '/api/collection/' . $followedId . '/follow');

        $data = $this->listCollection(['search' => $this->pfx, 'followed' => 'true']);

        $this->assertSame(1, $data['total']);
        $this->assertTrue($data['items'][0]['notificationsEnabled']);
    }

    // ── Sort ─────────────────────────────────────────────────────────────────

    public function testSortRatingDescFirstItemHasHighestRating(): void
    {
        $lowId  = $this->addToCollection($this->createManga($this->pfx . 'Low'));
        $highId = $this->addToCollection($this->createManga($this->pfx . 'High'));

        $this->jsonRequest('PATCH', '/api/collection/' . $lowId  . '/rating', ['rating' => 3]);
        $this->jsonRequest('PATCH', '/api/collection/' . $highId . '/rating', ['rating' => 9]);

        $data = $this->listCollection(['search' => $this->pfx, 'sort' => 'rating_desc']);

        $this->assertSame(9, $data['items'][0]['rating']);
    }

    public function testSortRatingAscFirstItemHasLowestRating(): void
    {
        $lowId  = $this->addToCollection($this->createManga($this->pfx . 'Low'));
        $highId = $this->addToCollection($this->createManga($this->pfx . 'High'));

        $this->jsonRequest('PATCH', '/api/collection/' . $lowId  . '/rating', ['rating' => 2]);
        $this->jsonRequest('PATCH', '/api/collection/' . $highId . '/rating', ['rating' => 8]);

        $data = $this->listCollection(['search' => $this->pfx, 'sort' => 'rating_asc']);

        $this->assertSame(2, $data['items'][0]['rating']);
    }

    public function testSortRatingAscNullRatingsAreLast(): void
    {
        $ratedId = $this->addToCollection($this->createManga($this->pfx . 'Rated'));
        $this->addToCollection($this->createManga($this->pfx . 'Unrated'));

        $this->jsonRequest('PATCH', '/api/collection/' . $ratedId . '/rating', ['rating' => 5]);

        $data = $this->listCollection(['search' => $this->pfx, 'sort' => 'rating_asc']);

        $this->assertSame(2, $data['total']);
        $this->assertNotNull($data['items'][0]['rating']);
        $this->assertNull($data['items'][1]['rating']);
    }

    // ── Combined filters ─────────────────────────────────────────────────────

    public function testAllFiltersCombinedReturnsIntersection(): void
    {
        $matchId = $this->addToCollection($this->createManga($this->pfx . 'Dragon Ball', 'shonen'));
        $this->jsonRequest('PATCH', '/api/collection/' . $matchId . '/status', ['status' => 'completed']);
        $this->jsonRequest('PATCH', '/api/collection/' . $matchId . '/follow');

        $this->addToCollection($this->createManga($this->pfx . 'Another Shonen', 'shonen'));
        $this->addToCollection($this->createManga($this->pfx . 'Seinen Entry',   'seinen'));

        $data = $this->listCollection([
            'search'        => $this->pfx . 'Dragon',
            'genre'         => 'shonen',
            'readingStatus' => 'completed',
            'followed'      => 'true',
        ]);

        $this->assertSame(1, $data['total']);
        $this->assertStringContainsString('Dragon Ball', $data['items'][0]['manga']['title']);
    }
}
