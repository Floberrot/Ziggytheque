<?php

declare(strict_types=1);

namespace App\Tests\Functional\Collection;

use App\Tests\Functional\AbstractApiTestCase;

final class ShelfControllerTest extends AbstractApiTestCase
{
    private function createManga(string $title = 'Test Manga'): string
    {
        $response = $this->jsonRequest('POST', '/api/manga', ['title' => $title, 'language' => 'fr']);
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

    // ── GET /api/shelf ───────────────────────────────────────────────────────

    public function testShelfRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/shelf', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testShelfReturnsEmptyArrayWhenNoOwnedVolumes(): void
    {
        $data = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/shelf'));
        $this->assertIsArray($data);
    }

    public function testShelfOnlyReturnsOwnedVolumes(): void
    {
        // Create manga with 2 volumes via sync
        $mangaId = $this->createManga('Shelf Manga');
        $entryId = $this->addToCollection($mangaId);
        $this->jsonRequest('POST', "/api/collection/{$entryId}/sync-volumes", ['upToVolume' => 2]);

        // Get the entry detail to find volume entry IDs
        $detail = json_decode((string) $this->jsonRequest('GET', "/api/collection/{$entryId}")->getContent(), true);
        $volumes = $detail['volumes'];
        $this->assertCount(2, $volumes);

        // Mark first volume as owned
        $this->jsonRequest('PATCH', "/api/collection/{$entryId}/volumes/{$volumes[0]['id']}/toggle", ['field' => 'isOwned']);

        $shelfData = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/shelf'));

        $entry = null;
        foreach ($shelfData as $item) {
            if ($item['id'] === $entryId) {
                $entry = $item;
                break;
            }
        }

        $this->assertNotNull($entry, 'Collection entry should appear in shelf when it has owned volumes');
        $this->assertCount(1, $entry['volumes'], 'Only owned volumes should be returned');
        $this->assertSame($volumes[0]['id'], $entry['volumes'][0]['id']);

        $this->assertArrayHasKey('manga', $entry);
        $this->assertArrayHasKey('title', $entry['manga']);
        $this->assertArrayHasKey('edition', $entry['manga']);
        $this->assertArrayHasKey('coverUrl', $entry['manga']);
        $this->assertArrayHasKey('number', $entry['volumes'][0]);
        $this->assertArrayHasKey('coverUrl', $entry['volumes'][0]);
    }

    public function testShelfExcludesEntriesWithNoOwnedVolumes(): void
    {
        $mangaId = $this->createManga('No Owned Manga');
        $entryId = $this->addToCollection($mangaId);
        $this->jsonRequest('POST', "/api/collection/{$entryId}/sync-volumes", ['upToVolume' => 1]);

        $shelfData = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/shelf'));

        foreach ($shelfData as $item) {
            $this->assertNotSame($entryId, $item['id'], 'Entry with no owned volumes must not appear in shelf');
        }
    }
}
