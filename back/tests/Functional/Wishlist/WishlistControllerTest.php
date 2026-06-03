<?php

declare(strict_types=1);

namespace App\Tests\Functional\Wishlist;

use App\Tests\Functional\AbstractApiTestCase;

final class WishlistControllerTest extends AbstractApiTestCase
{
    // ── Helpers ─────────────────────────────────────────────────────────────

    private function createMangaWithVolumes(int $volumes = 2): string
    {
        $response = $this->jsonRequest('POST', '/api/manga', [
            'title'        => 'Wishlist Manga ' . uniqid(),
            'language'     => 'fr',
            'totalVolumes' => $volumes,
        ]);
        $data = json_decode((string) $response->getContent(), true);
        return (string) $data['id'];
    }

    private function addToCollection(string $mangaId): string
    {
        $response = $this->jsonRequest('POST', '/api/collection', ['mangaId' => $mangaId]);
        $data     = json_decode((string) $response->getContent(), true);
        return (string) $data['id'];
    }

    private function wishVolumes(string $entryId): void
    {
        $this->jsonRequest('POST', '/api/collection/' . $entryId . '/add-to-wishlist');
    }

    // ── GET /api/wishlist ────────────────────────────────────────────────────

    public function testListRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/wishlist', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testListReturnsPaginatedShape(): void
    {
        $response = $this->jsonRequest('GET', '/api/wishlist');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('limit', $data);
        $this->assertIsArray($data['items']);
    }

    public function testListShowsEntriesWithWishedVolumes(): void
    {
        $mangaId = $this->createMangaWithVolumes(2);
        $entryId = $this->addToCollection($mangaId);
        $this->wishVolumes($entryId);

        $response = $this->jsonRequest('GET', '/api/wishlist');
        $data     = $this->assertJsonStatus(200, $response);

        $ids = array_column($data['items'], 'id');
        $this->assertContains($entryId, $ids);
    }

    public function testListFiltersBySearch(): void
    {
        $matchingId    = $this->createMangaWithVolumes(2);
        $nonMatchingId = $this->createMangaWithVolumes(2);

        $matchingEntry    = $this->addToCollection($matchingId);
        $nonMatchingEntry = $this->addToCollection($nonMatchingId);
        $this->wishVolumes($matchingEntry);
        $this->wishVolumes($nonMatchingEntry);

        // Both titles start with "Wishlist Manga "; narrow to the matching entry's exact title.
        $title = (string) json_decode(
            (string) $this->jsonRequest('GET', '/api/manga/' . $matchingId)->getContent(),
            true,
        )['title'];

        $response = $this->jsonRequest('GET', '/api/wishlist?search=' . urlencode($title));
        $data     = $this->assertJsonStatus(200, $response);

        $ids = array_column($data['items'], 'id');
        $this->assertContains($matchingEntry, $ids);
        $this->assertNotContains($nonMatchingEntry, $ids);
    }

    public function testListSearchWithNoMatchReturnsEmpty(): void
    {
        $response = $this->jsonRequest('GET', '/api/wishlist?search=' . urlencode('zzz-no-such-title-zzz'));
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertSame(0, $data['total']);
        $this->assertSame([], $data['items']);
    }

    // ── POST /api/wishlist/{id}/add-remaining ────────────────────────────────

    public function testAddRemaining(): void
    {
        $mangaId = $this->createMangaWithVolumes(2);
        $entryId = $this->addToCollection($mangaId);

        $response = $this->jsonRequest('POST', '/api/wishlist/' . $entryId . '/add-remaining');
        $this->assertSame(204, $response->getStatusCode());

        $wishlist = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/wishlist'));
        $found    = array_filter($wishlist['items'], static fn ($entry) => $entry['id'] === $entryId);
        $this->assertNotEmpty($found);
    }

    public function testAddRemainingNotFoundReturns404(): void
    {
        $response = $this->jsonRequest('POST', '/api/wishlist/bad-id/add-remaining');
        $this->assertJsonStatus(404, $response);
    }

    // ── DELETE /api/wishlist/{id} ────────────────────────────────────────────

    public function testClearWishlist(): void
    {
        $mangaId = $this->createMangaWithVolumes(2);
        $entryId = $this->addToCollection($mangaId);
        $this->wishVolumes($entryId);

        $response = $this->jsonRequest('DELETE', '/api/wishlist/' . $entryId);
        $this->assertSame(204, $response->getStatusCode());

        $wishlist = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/wishlist'));
        $found    = array_filter($wishlist['items'], static fn ($entry) => $entry['id'] === $entryId);
        $this->assertEmpty($found);
    }

    public function testClearNotFoundReturns404(): void
    {
        $response = $this->jsonRequest('DELETE', '/api/wishlist/bad-id');
        $this->assertJsonStatus(404, $response);
    }

    // ── POST /api/wishlist/{id}/volumes/{veId}/purchase ──────────────────────

    public function testPurchaseVolume(): void
    {
        $mangaId = $this->createMangaWithVolumes(1);
        $entryId = $this->addToCollection($mangaId);
        $this->wishVolumes($entryId);

        $detail = json_decode(
            (string) $this->jsonRequest('GET', '/api/collection/' . $entryId)->getContent(),
            true,
        );
        $veId = $detail['volumes'][0]['id'];

        $response = $this->jsonRequest(
            'POST',
            '/api/wishlist/' . $entryId . '/volumes/' . $veId . '/purchase',
        );
        $this->assertSame(204, $response->getStatusCode());

        $detail = json_decode(
            (string) $this->jsonRequest('GET', '/api/collection/' . $entryId)->getContent(),
            true,
        );
        $this->assertTrue($detail['volumes'][0]['isOwned']);
        $this->assertFalse($detail['volumes'][0]['isWished']);
    }

    public function testPurchaseVolumeNotFoundReturns404(): void
    {
        $mangaId = $this->createMangaWithVolumes(1);
        $entryId = $this->addToCollection($mangaId);

        $response = $this->jsonRequest('POST', '/api/wishlist/' . $entryId . '/volumes/bad-ve/purchase');
        $this->assertJsonStatus(404, $response);
    }
}
