<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\Functional\AbstractApiTestCase;
use App\Tests\Functional\Fixtures\UserFixtureFactory;

final class AdminUserControllerTest extends AbstractApiTestCase
{
    // ── GET /api/admin/users ──────────────────────────────────────────────

    public function testListUsersRequiresAdminUnlocked(): void
    {
        // $this->token is ROLE_ADMIN but NOT ROLE_ADMIN_UNLOCKED
        $response = $this->jsonRequest('GET', '/api/admin/users');
        $this->assertJsonStatus(403, $response);
    }

    public function testListUsersWithAdminUnlockedToken(): void
    {
        $gateResponse = $this->jsonRequest('POST', '/api/auth/gate', ['password' => 'ziggy123']);
        /** @var array{token?: string} $gateData */
        $gateData      = json_decode((string) $gateResponse->getContent(), true);
        $unlockedToken = $gateData['token'] ?? '';

        $this->client->request(
            'GET',
            '/api/admin/users',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $unlockedToken, 'HTTP_ACCEPT' => 'application/json'],
        );

        $data = $this->assertJsonStatus(200, $this->client->getResponse());
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('total', $data);
    }

    // ── GET /api/admin/users/{id} ─────────────────────────────────────────

    public function testGetUserReturns404ForUnknownId(): void
    {
        $unlockedToken = $this->getAdminUnlockedToken();

        $this->client->request(
            'GET',
            '/api/admin/users/non-existent-id',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $unlockedToken, 'HTTP_ACCEPT' => 'application/json'],
        );

        $this->assertJsonStatus(404, $this->client->getResponse());
    }

    // ── POST /api/admin/users/{id}/approve ────────────────────────────────

    public function testApproveUserTransitionsStatus(): void
    {
        $pendingUser   = UserFixtureFactory::createPendingUser(static::getContainer(), email: 'pending2@test.local');
        $unlockedToken = $this->getAdminUnlockedToken();

        $this->client->request(
            'POST',
            '/api/admin/users/' . $pendingUser->id . '/approve',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $unlockedToken, 'HTTP_ACCEPT' => 'application/json'],
        );

        $this->assertJsonStatus(200, $this->client->getResponse());
    }

    // ── DELETE /api/admin/users/{id} ─────────────────────────────────────

    public function testDeleteUserReturns204(): void
    {
        $user          = UserFixtureFactory::createActiveUser(static::getContainer(), email: 'deleteme@test.local');
        $unlockedToken = $this->getAdminUnlockedToken();

        $this->client->request(
            'DELETE',
            '/api/admin/users/' . $user->id,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $unlockedToken],
        );

        $this->assertSame(204, $this->client->getResponse()->getStatusCode());
    }

    // ── POST /api/admin/users/{id}/reset-link ────────────────────────────

    public function testGenerateResetLinkReturnsLink(): void
    {
        $user          = UserFixtureFactory::createActiveUser(static::getContainer(), email: 'resetme@test.local');
        $unlockedToken = $this->getAdminUnlockedToken();

        $this->client->request(
            'POST',
            '/api/admin/users/' . $user->id . '/reset-link',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $unlockedToken, 'HTTP_ACCEPT' => 'application/json'],
        );

        $data = $this->assertJsonStatus(200, $this->client->getResponse());
        $this->assertArrayHasKey('resetLink', $data);
        $this->assertStringContainsString('reset-password', (string) $data['resetLink']);
    }

    // ── Helper ────────────────────────────────────────────────────────────

    private function getAdminUnlockedToken(): string
    {
        $gateResponse = $this->jsonRequest('POST', '/api/auth/gate', ['password' => 'ziggy123']);
        /** @var array{token?: string} $data */
        $data = json_decode((string) $gateResponse->getContent(), true);

        return $data['token'] ?? '';
    }
}
