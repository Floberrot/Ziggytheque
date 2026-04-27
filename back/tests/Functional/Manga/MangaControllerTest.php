<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Tests\Functional\AbstractApiTestCase;

final class MangaControllerTest extends AbstractApiTestCase
{
    // ── Helpers ─────────────────────────────────────────────────────────────

    private function importManga(array $overrides = []): string
    {
        $payload  = array_merge([
            'title'        => 'One Piece',
            'language'     => 'fr',
            'edition'      => null,
            'author'       => 'Oda Eiichiro',
            'summary'      => 'A pirate story.',
            'coverUrl'     => null,
            'genre'        => 'shonen',
            'externalId'   => null,
            'totalVolumes' => null,
        ], $overrides);

        $response = $this->jsonRequest('POST', '/api/manga', $payload);
        $this->assertSame(201, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        return (string) $data['id'];
    }

    // ── GET /api/manga ───────────────────────────────────────────────────────

    public function testSearchRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testSearchReturnsEmptyList(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga');
        $data     = $this->assertJsonStatus(200, $response);
        $this->assertIsArray($data);
    }

    public function testSearchByQuery(): void
    {
        $this->importManga(['title' => 'Naruto']);
        $this->importManga(['title' => 'Bleach']);

        $response = $this->jsonRequest('GET', '/api/manga?q=Naruto');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertIsArray($data);
        $titles = array_column($data, 'title');
        $this->assertContains('Naruto', $titles);
    }

    // ── GET /api/manga/{id} ──────────────────────────────────────────────────

    public function testGetMangaById(): void
    {
        $id       = $this->importManga(['title' => 'Dragon Ball']);
        $response = $this->jsonRequest('GET', '/api/manga/' . $id);
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertSame($id, $data['id']);
        $this->assertSame('Dragon Ball', $data['title']);
        $this->assertArrayHasKey('volumes', $data);
    }

    public function testGetMangaNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga/nonexistent-id');
        $this->assertJsonStatus(404, $response);
    }

    // ── POST /api/manga ──────────────────────────────────────────────────────

    public function testImportMangaMinimal(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga', [
            'title'    => 'Minimal Manga',
            'language' => 'fr',
        ]);
        $data = $this->assertJsonStatus(201, $response);

        $this->assertArrayHasKey('id', $data);
        $this->assertIsString($data['id']);
    }

    public function testImportMangaWithVolumes(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga', [
            'title'        => 'Fullmetal Alchemist',
            'language'     => 'fr',
            'genre'        => 'shonen',
            'totalVolumes' => 3,
        ]);
        $data = $this->assertJsonStatus(201, $response);
        $id   = $data['id'];

        $detail = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/manga/' . $id));
        $this->assertCount(3, $detail['volumes']);
    }

    public function testImportMangaRequiresTitle(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga', ['language' => 'fr']);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function testImportMangaRequiresAuth(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga', ['title' => 'X', 'language' => 'fr'], auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    // ── PATCH /api/manga/{id} ────────────────────────────────────────────────

    public function testUpdateManga(): void
    {
        $id = $this->importManga(['title' => 'Old Title']);

        $response = $this->jsonRequest('PATCH', '/api/manga/' . $id, ['title' => 'New Title']);
        $this->assertSame(204, $response->getStatusCode());

        $detail = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/manga/' . $id));
        $this->assertSame('New Title', $detail['title']);
    }

    public function testUpdateMangaNotFound(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/manga/bad-id', ['title' => 'T']);
        $this->assertJsonStatus(404, $response);
    }

    // ── POST /api/manga/{id}/volumes ─────────────────────────────────────────

    public function testAddVolume(): void
    {
        $id = $this->importManga();

        $response = $this->jsonRequest('POST', '/api/manga/' . $id . '/volumes', [
            'number'      => 1,
            'coverUrl'    => null,
            'releaseDate' => null,
        ]);
        $data = $this->assertJsonStatus(201, $response);

        $this->assertArrayHasKey('id', $data);
    }

    public function testAddVolumeToNonExistentManga(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga/bad-id/volumes', ['number' => 1]);
        $this->assertJsonStatus(404, $response);
    }

    // ── PATCH /api/manga/{id}/volumes/{volumeId} ──────────────────────────────

    public function testUpdateVolume(): void
    {
        $mangaId = $this->importManga();

        $volData = $this->assertJsonStatus(201, $this->jsonRequest(
            'POST',
            '/api/manga/' . $mangaId . '/volumes',
            ['number' => 1],
        ));
        $volumeId = $volData['id'];

        $response = $this->jsonRequest(
            'PATCH',
            '/api/manga/' . $mangaId . '/volumes/' . $volumeId,
            ['price' => 7.99],
        );
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testUpdateVolumeNotFound(): void
    {
        $mangaId  = $this->importManga();
        $response = $this->jsonRequest('PATCH', '/api/manga/' . $mangaId . '/volumes/bad-vol', ['price' => 5.0]);
        $this->assertJsonStatus(404, $response);
    }

    // ── GET /api/manga/external ───────────────────────────────────────────────

    public function testSearchExternalReturnsEmpty(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga/external?q=test');
        $data     = $this->assertJsonStatus(200, $response);
        $this->assertSame([], $data);
    }

    public function testSearchExternalRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga/external?q=test', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    // ── GET /api/manga/volume-search ─────────────────────────────────────────

    public function testVolumeSearchReturnsEmpty(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga/volume-search?q=test');
        $data     = $this->assertJsonStatus(200, $response);
        $this->assertSame([], $data);
    }
}
