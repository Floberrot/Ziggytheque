<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Tests\Functional\AbstractApiTestCase;

final class GetVolumePricesTest extends AbstractApiTestCase
{
    private function importMangaWithVolume(): array
    {
        $importResponse = $this->jsonRequest('POST', '/api/manga', [
            'title'    => 'Berserk',
            'language' => 'fr',
            'edition'  => null,
            'author'   => 'Kentaro Miura',
        ]);
        $this->assertSame(201, $importResponse->getStatusCode());
        $mangaData = json_decode((string) $importResponse->getContent(), true);
        $mangaId   = (string) $mangaData['id'];

        $volumeResponse = $this->jsonRequest('POST', '/api/manga/' . $mangaId . '/volumes', [
            'number' => 1,
        ]);
        $this->assertSame(201, $volumeResponse->getStatusCode());
        $volumeData = json_decode((string) $volumeResponse->getContent(), true);
        $volumeId   = (string) $volumeData['id'];

        return ['mangaId' => $mangaId, 'volumeId' => $volumeId];
    }

    // ── GET /api/manga/{id}/volumes/{volumeId}/prices ────────────────────────

    public function testVolumePricesRequiresAuth(): void
    {
        $response = $this->jsonRequest(
            'GET',
            '/api/manga/nonexistent/volumes/nonexistent/prices',
            auth: false,
        );
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testVolumePricesReturns404ForUnknownManga(): void
    {
        $response = $this->jsonRequest(
            'GET',
            '/api/manga/nonexistent-manga-id/volumes/nonexistent-volume-id/prices',
        );
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testVolumePricesReturns404ForUnknownVolume(): void
    {
        $importResponse = $this->jsonRequest('POST', '/api/manga', [
            'title'    => 'Test Manga',
            'language' => 'fr',
            'edition'  => null,
            'author'   => null,
        ]);
        $this->assertSame(201, $importResponse->getStatusCode());
        $mangaData = json_decode((string) $importResponse->getContent(), true);
        $mangaId   = (string) $mangaData['id'];

        $response = $this->jsonRequest(
            'GET',
            '/api/manga/' . $mangaId . '/volumes/nonexistent-volume-id/prices',
        );
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testVolumePricesReturnsEmptyOffersAndHasIsbnFalseWhenNoIsbn(): void
    {
        ['mangaId' => $mangaId, 'volumeId' => $volumeId] = $this->importMangaWithVolume();

        $response = $this->jsonRequest(
            'GET',
            '/api/manga/' . $mangaId . '/volumes/' . $volumeId . '/prices',
        );
        $data = $this->assertJsonStatus(200, $response);

        $this->assertFalse($data['hasIsbn']);
        $this->assertSame([], $data['offers']);
    }

    public function testVolumePricesReturnsOffersWhenVolumeHasIsbn(): void
    {
        ['mangaId' => $mangaId, 'volumeId' => $volumeId] = $this->importMangaWithVolume();

        // Give the volume an ISBN
        $this->jsonRequest('PATCH', '/api/manga/' . $mangaId . '/volumes/' . $volumeId, [
            'isbn' => '9782723425483',
        ]);

        $response = $this->jsonRequest(
            'GET',
            '/api/manga/' . $mangaId . '/volumes/' . $volumeId . '/prices',
        );
        $data = $this->assertJsonStatus(200, $response);

        $this->assertTrue($data['hasIsbn']);
        $this->assertArrayHasKey('offers', $data);
        $this->assertArrayHasKey('marketplace', $data);
        // NullPriceProvider returns empty offers — just check shape is correct
        $this->assertIsArray($data['offers']);
    }

    public function testVolumePricesRespectsMarketplaceQueryParam(): void
    {
        ['mangaId' => $mangaId, 'volumeId' => $volumeId] = $this->importMangaWithVolume();

        $this->jsonRequest('PATCH', '/api/manga/' . $mangaId . '/volumes/' . $volumeId, [
            'isbn' => '9782723425483',
        ]);

        $response = $this->jsonRequest(
            'GET',
            '/api/manga/' . $mangaId . '/volumes/' . $volumeId . '/prices?marketplace=EBAY_US',
        );
        $data = $this->assertJsonStatus(200, $response);

        $this->assertSame('EBAY_US', $data['marketplace']);
    }
}
