<?php

declare(strict_types=1);

namespace App\Tests\Functional\Share;

use App\Tests\Functional\AbstractApiTestCase;
use App\Tests\Functional\Fixtures\UserFixtureFactory;

final class ShareControllerTest extends AbstractApiTestCase
{
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

    // ── POST /api/share ───────────────────────────────────────────────────────

    public function testCreateShareReturnsTokenAndUrl(): void
    {
        $response = $this->jsonRequest('POST', '/api/share');
        $data     = $this->assertJsonStatus(201, $response);

        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('url', $data);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $data['token']);
        $this->assertStringEndsWith('/share/' . $data['token'], $data['url']);
    }

    public function testCreateShareRequiresAuth(): void
    {
        $response = $this->jsonRequest('POST', '/api/share', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    // ── GET /api/share/{token} ─────────────────────────────────────────────────

    public function testPublicGetReturnsFrozenSnapshot(): void
    {
        $this->addToCollection($this->createManga(title: 'Shared Series'));

        $token = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/share'))['token'];

        // No auth header — the snapshot is publicly readable.
        $response = $this->jsonRequest('GET', '/api/share/' . $token, auth: false);
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertArrayHasKey('ownerName', $data);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertArrayHasKey('stats', $data);

        $stats = $data['stats'];
        $this->assertArrayHasKey('totalMangas', $stats);
        $this->assertArrayHasKey('totalOwned', $stats);
        $this->assertArrayHasKey('totalRead', $stats);
        $this->assertArrayHasKey('totalWishlist', $stats);
        $this->assertArrayHasKey('genreBreakdown', $stats);

        // Privacy: monetary value and covers are never exposed publicly.
        $this->assertArrayNotHasKey('ownedValue', $stats);
        $this->assertArrayNotHasKey('totalValue', $stats);
        $this->assertArrayNotHasKey('recentAdditions', $stats);

        $this->assertSame(1, $stats['totalMangas']);
    }

    public function testSnapshotScopesStatsToCreatingUser(): void
    {
        // The setUp admin shares an empty collection.
        $token = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/share'))['token'];

        $stats = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/share/' . $token, auth: false))['stats'];
        $this->assertSame(0, $stats['totalMangas']);
    }

    public function testSnapshotIsImmutableAfterCollectionChanges(): void
    {
        $token = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/share'))['token'];

        // Add a series after the snapshot was frozen.
        $this->addToCollection($this->createManga(title: 'Added Later'));

        $stats = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/share/' . $token, auth: false))['stats'];
        $this->assertSame(0, $stats['totalMangas'], 'Snapshot must reflect the moment it was taken');
    }

    public function testUnknownTokenReturns404(): void
    {
        // 32 hex chars that match the route requirement but do not exist.
        $response = $this->jsonRequest('GET', '/api/share/' . str_repeat('a', 32), auth: false);
        $this->assertJsonStatus(404, $response);
    }

    public function testMalformedTokenReturns404(): void
    {
        // Does not match the {token} requirement → no route matches.
        $response = $this->jsonRequest('GET', '/api/share/not-a-valid-token', auth: false);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testOtherUserCanViewSnapshotButCannotCreateUnauthenticated(): void
    {
        $this->addToCollection($this->createManga(title: 'Owner Series'));
        $token = $this->assertJsonStatus(201, $this->jsonRequest('POST', '/api/share'))['token'];

        // A second account can still read the public link.
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'viewer@test.local');
        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenForUser('viewer@test.local'),
            'HTTP_ACCEPT'        => 'application/json',
        ];
        $this->client->request('GET', '/api/share/' . $token, [], [], $headers);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
    }
}
