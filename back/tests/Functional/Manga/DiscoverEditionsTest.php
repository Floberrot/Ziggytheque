<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Tests\Functional\AbstractApiTestCase;
use App\Tests\Functional\Fixtures\UserFixtureFactory;

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

    /**
     * The limit is keyed on the authenticated user, so one account cannot spend
     * another's budget — which is what an IP key did behind the proxy.
     */
    public function testEditionsRateLimitIsPerUser(): void
    {
        $otherUser = UserFixtureFactory::createActiveUser(
            static::getContainer(),
            email: 'editions-other@test.local',
        );

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $response = $this->jsonRequest('GET', '/api/manga/editions?q=berserk');
            $this->assertJsonStatus(200, $response);
        }

        $response = $this->jsonRequest('GET', '/api/manga/editions?q=berserk');
        $this->assertJsonStatus(429, $response);

        // A different account still has its own full budget.
        $otherToken = $this->tokenForUser($otherUser->email);
        $this->client->request(
            'GET',
            '/api/manga/editions?q=berserk',
            [],
            [],
            [
                'CONTENT_TYPE'       => 'application/json',
                'HTTP_ACCEPT'        => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $otherToken,
            ],
        );

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
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
