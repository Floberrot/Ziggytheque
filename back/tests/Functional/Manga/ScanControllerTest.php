<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Tests\Doubles\Manga\InMemoryScanResultPublisher;
use App\Tests\Functional\AbstractApiTestCase;
use App\Tests\Functional\Fixtures\HandTypedSeriesTrait;

final class ScanControllerTest extends AbstractApiTestCase
{
    use HandTypedSeriesTrait;

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

    /** @return array{string, string} the series id and the id of its one tome */
    private function seriesWithOneTome(): array
    {
        $series = $this->collectHandTypedSeries('Scan Manga');

        return [$series['mangaId'], $series['volumeIds'][0]];
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

    public function testCreateFreeSessionWithoutTargetReturnsTokens(): void
    {
        $data = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/scan/sessions', ['mangaId' => null]));

        $this->assertArrayHasKey('scanToken', $data);
        $this->assertStringContainsString($data['sessionId'], $data['topic']);
    }

    public function testCreateSessionWithAMangaButNoVolumeIsNotFound(): void
    {
        $mangaId = $this->importManga();

        $this->assertJsonStatus(404, $this->jsonRequest('POST', '/api/scan/sessions', ['mangaId' => $mangaId]));
    }

    public function testCreateSessionReturnsTokens(): void
    {
        [$mangaId, $volumeId] = $this->seriesWithOneTome();

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
        [$mangaId, $volumeId] = $this->seriesWithOneTome();

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
        [$mangaId, $volumeId] = $this->seriesWithOneTome();

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
