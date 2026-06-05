<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Tests\Functional\AbstractApiTestCase;

final class DiscoverEditionsTest extends AbstractApiTestCase
{
    // ── GET /api/manga/editions ──────────────────────────────────────────────

    public function testEditionsRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga/editions?q=berserk', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testEditionsReturnsEmptyArrayWithNullProvider(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga/editions?q=berserk');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertIsArray($data);
        $this->assertSame([], $data);
    }

    public function testEditionsWithAuthorAndLanguageParams(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga/editions?q=berserk&author=Miura&language=fr');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertIsArray($data);
    }

    // ── GET /api/manga/{id}/editions ─────────────────────────────────────────

    public function testMangaEditionsRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga/nonexistent-id/editions', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testMangaEditionsReturns404ForUnknownManga(): void
    {
        $response = $this->jsonRequest('GET', '/api/manga/nonexistent-id/editions');
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testMangaEditionsReturnsEmptyArrayForExistingManga(): void
    {
        $importResponse = $this->jsonRequest('POST', '/api/manga', [
            'title'    => 'Berserk',
            'language' => 'fr',
            'edition'  => null,
            'author'   => 'Kentaro Miura',
        ]);
        $this->assertSame(201, $importResponse->getStatusCode());
        $importData = json_decode((string) $importResponse->getContent(), true);
        $mangaId    = (string) $importData['id'];

        $response = $this->jsonRequest('GET', '/api/manga/' . $mangaId . '/editions');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertIsArray($data);
        $this->assertSame([], $data);
    }
}
