<?php

declare(strict_types=1);

namespace App\Tests\Functional\Collection;

use App\Tests\Functional\AbstractApiTestCase;

final class CollectionControllerTest extends AbstractApiTestCase
{
    // ── Helpers ─────────────────────────────────────────────────────────────

    private function createManga(int $volumes = 0, string $title = 'Test Manga'): string
    {
        $response = $this->jsonRequest('POST', '/api/manga', [
            'title'        => $title,
            'language'     => 'fr',
            'totalVolumes' => $volumes > 0 ? $volumes : null,
        ]);
        $data = json_decode((string) $response->getContent(), true);
        return (string) $data['id'];
    }

    private function addToCollection(string $mangaId): string
    {
        $response = $this->jsonRequest('POST', '/api/collection', ['mangaId' => $mangaId]);
        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        return (string) $data['id'];
    }

    private function getDetail(string $entryId): array
    {
        $response = $this->jsonRequest('GET', '/api/collection/' . $entryId);
        $this->assertSame(200, $response->getStatusCode());
        return (array) json_decode((string) $response->getContent(), true);
    }

    // ── GET /api/collection ──────────────────────────────────────────────────

    public function testListRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/collection', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testListReturnsPaginatedShape(): void
    {
        $response = $this->jsonRequest('GET', '/api/collection');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('limit', $data);
        $this->assertIsArray($data['items']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(20, $data['limit']);
    }

    // ── POST /api/collection ─────────────────────────────────────────────────

    public function testAddToCollection(): void
    {
        $mangaId  = $this->createManga();
        $response = $this->jsonRequest('POST', '/api/collection', ['mangaId' => $mangaId]);
        $data     = $this->assertJsonStatus(201, $response);

        $this->assertArrayHasKey('id', $data);
        $this->assertIsString($data['id']);
    }

    public function testAddToCollectionCreatesVolumeEntries(): void
    {
        $mangaId = $this->createManga(volumes: 3);
        $entryId = $this->addToCollection($mangaId);

        $detail = $this->getDetail($entryId);
        $this->assertCount(3, $detail['volumes']);
    }

    public function testAddNonExistentMangaReturns404(): void
    {
        $response = $this->jsonRequest('POST', '/api/collection', ['mangaId' => 'bad-id']);
        $this->assertJsonStatus(404, $response);
    }

    public function testAddRequiresAuth(): void
    {
        $response = $this->jsonRequest('POST', '/api/collection', ['mangaId' => 'x'], auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    // ── GET /api/collection/{id} ─────────────────────────────────────────────

    public function testGetDetail(): void
    {
        $mangaId = $this->createManga(volumes: 2);
        $entryId = $this->addToCollection($mangaId);

        $detail = $this->getDetail($entryId);

        $this->assertSame($entryId, $detail['id']);
        $this->assertArrayHasKey('manga', $detail);
        $this->assertArrayHasKey('volumes', $detail);
        $this->assertSame('not_started', $detail['readingStatus']);
    }

    public function testGetDetailNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/collection/nonexistent');
        $this->assertJsonStatus(404, $response);
    }

    // ── DELETE /api/collection/{id} ──────────────────────────────────────────

    public function testRemoveFromCollection(): void
    {
        $mangaId = $this->createManga();
        $entryId = $this->addToCollection($mangaId);

        $response = $this->jsonRequest('DELETE', '/api/collection/' . $entryId);
        $this->assertSame(204, $response->getStatusCode());

        $response = $this->jsonRequest('GET', '/api/collection/' . $entryId);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testRemoveNotFoundReturns404(): void
    {
        $response = $this->jsonRequest('DELETE', '/api/collection/nonexistent');
        $this->assertJsonStatus(404, $response);
    }

    // ── PATCH /api/collection/{id}/status ────────────────────────────────────

    public function testUpdateReadingStatus(): void
    {
        $mangaId = $this->createManga();
        $entryId = $this->addToCollection($mangaId);

        $response = $this->jsonRequest('PATCH', '/api/collection/' . $entryId . '/status', ['status' => 'in_progress']);
        $this->assertSame(204, $response->getStatusCode());

        $detail = $this->getDetail($entryId);
        $this->assertSame('in_progress', $detail['readingStatus']);
    }

    public function testUpdateStatusInvalidValueReturns422(): void
    {
        $mangaId = $this->createManga();
        $entryId = $this->addToCollection($mangaId);

        $response = $this->jsonRequest('PATCH', '/api/collection/' . $entryId . '/status', ['status' => 'invalid']);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function testUpdateStatusNotFoundReturns404(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/collection/bad/status', ['status' => 'completed']);
        $this->assertJsonStatus(404, $response);
    }

    // ── PATCH /api/collection/{id}/rating ────────────────────────────────────

    public function testUpdateRating(): void
    {
        $mangaId = $this->createManga();
        $entryId = $this->addToCollection($mangaId);

        $response = $this->jsonRequest('PATCH', '/api/collection/' . $entryId . '/rating', ['rating' => 8]);
        $this->assertSame(204, $response->getStatusCode());

        $detail = $this->getDetail($entryId);
        $this->assertSame(8, $detail['rating']);
    }

    public function testUpdateRatingInvalidReturns422(): void
    {
        $mangaId = $this->createManga();
        $entryId = $this->addToCollection($mangaId);

        // Out of range (> 10)
        $response = $this->jsonRequest('PATCH', '/api/collection/' . $entryId . '/rating', ['rating' => 15]);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function testUpdateRatingNotFoundReturns404(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/collection/bad/rating', ['rating' => 5]);
        $this->assertJsonStatus(404, $response);
    }

    // ── PATCH /api/collection/{id}/volumes/{veId}/toggle ─────────────────────

    public function testToggleIsOwned(): void
    {
        $mangaId = $this->createManga(volumes: 1);
        $entryId = $this->addToCollection($mangaId);
        $detail  = $this->getDetail($entryId);
        $veId    = $detail['volumes'][0]['id'];

        $response = $this->jsonRequest(
            'PATCH',
            '/api/collection/' . $entryId . '/volumes/' . $veId . '/toggle',
            ['field' => 'isOwned'],
        );
        $this->assertSame(204, $response->getStatusCode());

        $detail = $this->getDetail($entryId);
        $this->assertTrue($detail['volumes'][0]['isOwned']);
        $this->assertSame(1, $detail['ownedCount']);
    }

    public function testToggleIsRead(): void
    {
        $mangaId = $this->createManga(volumes: 1);
        $entryId = $this->addToCollection($mangaId);
        $detail  = $this->getDetail($entryId);
        $veId    = $detail['volumes'][0]['id'];

        $this->jsonRequest(
            'PATCH',
            '/api/collection/' . $entryId . '/volumes/' . $veId . '/toggle',
            ['field' => 'isRead'],
        );

        $detail = $this->getDetail($entryId);
        $this->assertTrue($detail['volumes'][0]['isRead']);
    }

    public function testToggleIsWished(): void
    {
        $mangaId = $this->createManga(volumes: 1);
        $entryId = $this->addToCollection($mangaId);
        $detail  = $this->getDetail($entryId);
        $veId    = $detail['volumes'][0]['id'];

        $this->jsonRequest(
            'PATCH',
            '/api/collection/' . $entryId . '/volumes/' . $veId . '/toggle',
            ['field' => 'isWished'],
        );

        $detail = $this->getDetail($entryId);
        $this->assertTrue($detail['volumes'][0]['isWished']);
    }

    public function testToggleIsAnnounced(): void
    {
        $mangaId = $this->createManga(volumes: 1);
        $entryId = $this->addToCollection($mangaId);
        $detail  = $this->getDetail($entryId);
        $veId    = $detail['volumes'][0]['id'];

        $this->jsonRequest(
            'PATCH',
            '/api/collection/' . $entryId . '/volumes/' . $veId . '/toggle',
            ['field' => 'isAnnounced'],
        );

        $detail = $this->getDetail($entryId);
        $this->assertTrue($detail['volumes'][0]['isAnnounced']);
    }

    public function testToggleOwnedClearsWishedFlag(): void
    {
        $mangaId = $this->createManga(volumes: 1);
        $entryId = $this->addToCollection($mangaId);
        $detail  = $this->getDetail($entryId);
        $veId    = $detail['volumes'][0]['id'];
        $url     = '/api/collection/' . $entryId . '/volumes/' . $veId . '/toggle';

        // First wish the volume
        $this->jsonRequest('PATCH', $url, ['field' => 'isWished']);
        // Then mark as owned — should clear wished
        $this->jsonRequest('PATCH', $url, ['field' => 'isOwned']);

        $detail = $this->getDetail($entryId);
        $this->assertTrue($detail['volumes'][0]['isOwned']);
        $this->assertFalse($detail['volumes'][0]['isWished']);
    }

    public function testToggleInvalidFieldReturns422(): void
    {
        $mangaId = $this->createManga(volumes: 1);
        $entryId = $this->addToCollection($mangaId);
        $detail  = $this->getDetail($entryId);
        $veId    = $detail['volumes'][0]['id'];

        $response = $this->jsonRequest(
            'PATCH',
            '/api/collection/' . $entryId . '/volumes/' . $veId . '/toggle',
            ['field' => 'badField'],
        );
        $this->assertSame(422, $response->getStatusCode());
    }

    public function testToggleVolumeEntryNotFoundReturns404(): void
    {
        $mangaId = $this->createManga();
        $entryId = $this->addToCollection($mangaId);

        $response = $this->jsonRequest(
            'PATCH',
            '/api/collection/' . $entryId . '/volumes/bad-ve/toggle',
            ['field' => 'isOwned'],
        );
        $this->assertJsonStatus(404, $response);
    }

    // ── POST /api/collection/{id}/add-to-wishlist ─────────────────────────────

    public function testAddRemainingToWishlist(): void
    {
        $mangaId = $this->createManga(volumes: 2);
        $entryId = $this->addToCollection($mangaId);

        $response = $this->jsonRequest('POST', '/api/collection/' . $entryId . '/add-to-wishlist');
        $this->assertSame(204, $response->getStatusCode());

        $detail = $this->getDetail($entryId);
        $wished = array_filter($detail['volumes'], static fn ($v) => $v['isWished'] === true);
        $this->assertCount(2, $wished);
    }

    public function testAddRemainingToWishlistNotFoundReturns404(): void
    {
        $response = $this->jsonRequest('POST', '/api/collection/bad/add-to-wishlist');
        $this->assertJsonStatus(404, $response);
    }

    // ── POST /api/collection/{id}/volumes/{veId}/purchase ────────────────────

    public function testPurchaseVolume(): void
    {
        $mangaId = $this->createManga(volumes: 1);
        $entryId = $this->addToCollection($mangaId);
        $detail  = $this->getDetail($entryId);
        $veId    = $detail['volumes'][0]['id'];

        // Wish it first
        $this->jsonRequest(
            'PATCH',
            '/api/collection/' . $entryId . '/volumes/' . $veId . '/toggle',
            ['field' => 'isWished'],
        );

        $response = $this->jsonRequest(
            'POST',
            '/api/collection/' . $entryId . '/volumes/' . $veId . '/purchase',
        );
        $this->assertSame(204, $response->getStatusCode());

        $detail = $this->getDetail($entryId);
        $this->assertTrue($detail['volumes'][0]['isOwned']);
        $this->assertFalse($detail['volumes'][0]['isWished']);
    }

    public function testPurchaseVolumeNotFoundReturns404(): void
    {
        $mangaId = $this->createManga();
        $entryId = $this->addToCollection($mangaId);

        $response = $this->jsonRequest('POST', '/api/collection/' . $entryId . '/volumes/bad-ve/purchase');
        $this->assertJsonStatus(404, $response);
    }

    // ── PATCH /api/collection/{id}/batch-price ────────────────────────────────

    public function testBatchSetPrice(): void
    {
        $mangaId = $this->createManga(volumes: 2);
        $entryId = $this->addToCollection($mangaId);

        $response = $this->jsonRequest('PATCH', '/api/collection/' . $entryId . '/batch-price', ['price' => 8.99]);
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testBatchSetPriceNotFoundReturns404(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/collection/bad/batch-price', ['price' => 5.0]);
        $this->assertJsonStatus(404, $response);
    }

    // ── PATCH /api/collection/{id}/follow ────────────────────────────────────

    public function testToggleFollow(): void
    {
        $mangaId = $this->createManga();
        $entryId = $this->addToCollection($mangaId);

        $response = $this->jsonRequest('PATCH', '/api/collection/' . $entryId . '/follow');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertArrayHasKey('notificationsEnabled', $data);
        $this->assertTrue($data['notificationsEnabled']);
    }

    public function testToggleFollowTwiceDisables(): void
    {
        $mangaId = $this->createManga();
        $entryId = $this->addToCollection($mangaId);
        $url     = '/api/collection/' . $entryId . '/follow';

        $data1 = $this->assertJsonStatus(200, $this->jsonRequest('PATCH', $url));
        $data2 = $this->assertJsonStatus(200, $this->jsonRequest('PATCH', $url));

        $this->assertTrue($data1['notificationsEnabled']);
        $this->assertFalse($data2['notificationsEnabled']);
    }

    public function testToggleFollowNotFoundReturns404(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/collection/bad/follow');
        $this->assertJsonStatus(404, $response);
    }

    // ── POST /api/collection/{id}/sync-volumes ────────────────────────────────

    public function testSyncVolumes(): void
    {
        $mangaId = $this->createManga(volumes: 1);
        $entryId = $this->addToCollection($mangaId);

        $response = $this->jsonRequest('POST', '/api/collection/' . $entryId . '/sync-volumes', ['upToVolume' => 3]);
        $this->assertSame(204, $response->getStatusCode());

        $detail = $this->getDetail($entryId);
        $this->assertCount(3, $detail['volumes']);
    }

    public function testSyncVolumesNoBody(): void
    {
        $mangaId = $this->createManga(volumes: 2);
        $entryId = $this->addToCollection($mangaId);

        $response = $this->jsonRequest('POST', '/api/collection/' . $entryId . '/sync-volumes');
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testSyncVolumesNotFoundReturns404(): void
    {
        $response = $this->jsonRequest('POST', '/api/collection/bad/sync-volumes', ['upToVolume' => 5]);
        $this->assertJsonStatus(404, $response);
    }
}
