<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Tests\Functional\AbstractApiTestCase;

final class VolumeFaceUploadControllerTest extends AbstractApiTestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR4nGNgYGAAAAAEAAH2FzhVAAAAAElFTkSuQmCC';

    /** @return array{string, string} [mangaId, volumeId] */
    private function createMangaWithVolume(): array
    {
        $manga = $this->assertJsonStatus(201, $this->jsonRequest(
            'POST',
            '/api/manga',
            ['title' => 'Face Manga', 'language' => 'fr'],
        ));
        $volume = $this->assertJsonStatus(201, $this->jsonRequest(
            'POST',
            '/api/manga/' . $manga['id'] . '/volumes',
            ['number' => 1],
        ));

        return [(string) $manga['id'], (string) $volume['id']];
    }

    public function testUploadsBackCoverFace(): void
    {
        [$mangaId, $volumeId] = $this->createMangaWithVolume();

        $response = $this->jsonRequest('POST', "/api/manga/{$mangaId}/volumes/{$volumeId}/face", [
            'face' => 'back',
            'image' => self::PNG_1X1,
            'contentType' => 'image/png',
        ]);
        $this->assertSame(204, $response->getStatusCode());

        $detail = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/manga/' . $mangaId));
        $this->assertNotNull($detail['volumes'][0]['backCoverUrl']);
        $this->assertStringContainsString('storage.test', (string) $detail['volumes'][0]['backCoverUrl']);
    }

    public function testUploadsSpineFace(): void
    {
        [$mangaId, $volumeId] = $this->createMangaWithVolume();

        $this->jsonRequest('POST', "/api/manga/{$mangaId}/volumes/{$volumeId}/face", [
            'face' => 'spine',
            'image' => self::PNG_1X1,
            'contentType' => 'image/png',
        ]);

        $detail = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/manga/' . $mangaId));
        $this->assertNotNull($detail['volumes'][0]['spineUrl']);
    }

    public function testRejectsUnknownFace(): void
    {
        [$mangaId, $volumeId] = $this->createMangaWithVolume();

        $response = $this->jsonRequest('POST', "/api/manga/{$mangaId}/volumes/{$volumeId}/face", [
            'face' => 'middle',
            'image' => self::PNG_1X1,
            'contentType' => 'image/png',
        ]);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function testRequiresImage(): void
    {
        [$mangaId, $volumeId] = $this->createMangaWithVolume();

        $response = $this->jsonRequest('POST', "/api/manga/{$mangaId}/volumes/{$volumeId}/face", [
            'face' => 'cover',
            'contentType' => 'image/png',
        ]);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function testVolumeNotFound(): void
    {
        [$mangaId] = $this->createMangaWithVolume();

        $response = $this->jsonRequest('POST', "/api/manga/{$mangaId}/volumes/missing/face", [
            'face' => 'cover',
            'image' => self::PNG_1X1,
            'contentType' => 'image/png',
        ]);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testRequiresAuth(): void
    {
        $response = $this->jsonRequest(
            'POST',
            '/api/manga/x/volumes/y/face',
            ['face' => 'cover', 'image' => self::PNG_1X1, 'contentType' => 'image/png'],
            auth: false,
        );
        $this->assertSame(401, $response->getStatusCode());
    }
}
