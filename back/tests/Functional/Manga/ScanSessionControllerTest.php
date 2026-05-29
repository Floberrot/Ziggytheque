<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Tests\Doubles\Manga\InMemoryScanSessionPublisher;
use App\Tests\Functional\AbstractApiTestCase;

final class ScanSessionControllerTest extends AbstractApiTestCase
{
    // ── POST /api/manga/scan-session ─────────────────────────────────────────

    public function testStartScanSessionReturns202WithSessionData(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga/scan-session');
        $data     = $this->assertJsonStatus(202, $response);

        $this->assertArrayHasKey('sessionId', $data);
        $this->assertArrayHasKey('mercureUrl', $data);
        $this->assertArrayHasKey('subscriberToken', $data);
        $this->assertArrayHasKey('topic', $data);
        $this->assertIsString($data['sessionId']);
        $this->assertStringContainsString($data['sessionId'], $data['topic']);
    }

    public function testStartScanSessionRequiresAuth(): void
    {
        $response = $this->jsonRequest('POST', '/api/manga/scan-session', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    // ── POST /api/manga/scan-session/{sessionId}/isbn ─────────────────────────

    public function testPublishScannedIsbnReturns202(): void
    {
        $sessionData = $this->assertJsonStatus(202, $this->jsonRequest('POST', '/api/manga/scan-session'));
        $sessionId   = $sessionData['sessionId'];

        $response = $this->jsonRequest(
            'POST',
            '/api/manga/scan-session/' . $sessionId . '/isbn',
            ['isbn' => '9782344020814'],
        );
        $this->assertSame(202, $response->getStatusCode());

        /** @var InMemoryScanSessionPublisher $publisher */
        $publisher = static::getContainer()->get(InMemoryScanSessionPublisher::class);
        $this->assertCount(1, $publisher->published);
        $this->assertSame($sessionId, $publisher->published[0]['sessionId']);
        $this->assertSame('9782344020814', $publisher->published[0]['isbn']);
    }

    public function testPublishScannedIsbnWithInvalidIsbnReturns422(): void
    {
        $sessionData = $this->assertJsonStatus(202, $this->jsonRequest('POST', '/api/manga/scan-session'));
        $sessionId   = $sessionData['sessionId'];

        $response = $this->jsonRequest(
            'POST',
            '/api/manga/scan-session/' . $sessionId . '/isbn',
            ['isbn' => '123'],
        );
        $this->assertSame(422, $response->getStatusCode());
    }

    public function testPublishScannedIsbnRequiresAuth(): void
    {
        $response = $this->jsonRequest(
            'POST',
            '/api/manga/scan-session/some-session-id/isbn',
            ['isbn' => '9782344020814'],
            auth: false,
        );
        $this->assertSame(401, $response->getStatusCode());
    }
}
