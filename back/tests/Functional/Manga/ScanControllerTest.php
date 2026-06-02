<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Tests\Doubles\Manga\InMemoryScanResultPublisher;
use App\Tests\Functional\AbstractApiTestCase;

final class ScanControllerTest extends AbstractApiTestCase
{
    private function importManga(array $overrides = []): string
    {
        $payload = array_merge([
            'title'    => 'Scan Manga',
            'language' => 'fr',
        ], $overrides);

        $response = $this->jsonRequest('POST', '/api/manga', $payload);
        $this->assertSame(201, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        return (string) $data['id'];
    }

    private function addVolume(string $mangaId, int $number = 1): string
    {
        $response = $this->jsonRequest('POST', '/api/manga/' . $mangaId . '/volumes', ['number' => $number]);
        $this->assertSame(201, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        return (string) $data['id'];
    }

    public function testCreateSessionRequiresAuth(): void
    {
        $response = $this->jsonRequest('POST', '/api/scan/sessions', ['mangaId' => 'x', 'volumeId' => 'y'], auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testCreateSessionMangaNotFound(): void
    {
        $response = $this->jsonRequest('POST', '/api/scan/sessions', ['mangaId' => 'nonexistent', 'volumeId' => 'v']);
        $this->assertJsonStatus(404, $response);
    }

    public function testCreateSessionVolumeNotFound(): void
    {
        $mangaId = $this->importManga();

        $response = $this->jsonRequest('POST', '/api/scan/sessions', ['mangaId' => $mangaId, 'volumeId' => 'bad-vol']);
        $this->assertJsonStatus(404, $response);
    }

    public function testCreateSessionReturnsTokens(): void
    {
        $mangaId = $this->importManga();
        $volumeId = $this->addVolume($mangaId);

        $data = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/scan/sessions', [
            'mangaId' => $mangaId,
            'volumeId' => $volumeId,
        ]));

        $this->assertArrayHasKey('sessionId', $data);
        $this->assertArrayHasKey('scanToken', $data);
        $this->assertArrayHasKey('mercureUrl', $data);
        $this->assertArrayHasKey('subscriberToken', $data);
        $this->assertArrayHasKey('topic', $data);
        $this->assertStringContainsString($data['sessionId'], $data['topic']);
    }

    public function testSubmitPublishesIsbn(): void
    {
        $mangaId = $this->importManga();
        $volumeId = $this->addVolume($mangaId);

        $sessionData = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/scan/sessions', [
            'mangaId' => $mangaId,
            'volumeId' => $volumeId,
        ]));

        $response = $this->jsonRequest('POST', '/api/scan/submit', [
            'scanToken' => $sessionData['scanToken'],
            'isbn' => '9782811645632',
        ], auth: false);

        $this->assertSame(204, $response->getStatusCode());

        /** @var InMemoryScanResultPublisher $publisher */
        $publisher = static::getContainer()->get(InMemoryScanResultPublisher::class);
        $this->assertCount(1, $publisher->published);
        $this->assertSame('9782811645632', $publisher->published[0]['isbn']);
        $this->assertSame($sessionData['sessionId'], $publisher->published[0]['sessionId']);
    }

    public function testSubmitInvalidTokenReturns410(): void
    {
        $response = $this->jsonRequest('POST', '/api/scan/submit', [
            'scanToken' => 'garbage',
            'isbn' => '9782811645632',
        ], auth: false);

        $this->assertSame(410, $response->getStatusCode());
    }

    public function testSubmitInvalidIsbnReturns422(): void
    {
        $mangaId = $this->importManga();
        $volumeId = $this->addVolume($mangaId);

        $sessionData = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/scan/sessions', [
            'mangaId' => $mangaId,
            'volumeId' => $volumeId,
        ]));

        $response = $this->jsonRequest('POST', '/api/scan/submit', [
            'scanToken' => $sessionData['scanToken'],
            'isbn' => 'xxx',
        ], auth: false);

        $this->assertSame(422, $response->getStatusCode());
    }
}
