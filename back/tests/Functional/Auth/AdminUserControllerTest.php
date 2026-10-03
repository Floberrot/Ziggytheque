<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\Functional\AbstractApiTestCase;
use App\Tests\Functional\Fixtures\UserFixtureFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mime\Email;

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
        $gateResponse = $this->jsonRequest('POST', '/api/auth/gate', ['password' => 'test-gate-password']);
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

    public function testListUsersRefusesAnUnknownStatus(): void
    {
        $this->client->request('GET', '/api/admin/users?status=bogus', [], [], $this->unlockedHeaders());

        $this->assertJsonStatus(422, $this->client->getResponse());
    }

    public function testListUsersKeepsThePageInRange(): void
    {
        $this->client->request('GET', '/api/admin/users?page=0&limit=100000', [], [], $this->unlockedHeaders());

        $data = $this->assertJsonStatus(200, $this->client->getResponse());
        $this->assertSame(1, $data['page']);
        $this->assertSame(100, $data['limit']);
    }

    /** @return array<string, string> */
    private function unlockedHeaders(): array
    {
        $gatePassword = (string) ($_SERVER['GATE_PASSWORD'] ?? $_ENV['GATE_PASSWORD'] ?? '');
        $gateResponse = $this->jsonRequest('POST', '/api/auth/gate', ['password' => $gatePassword]);
        /** @var array{token?: string} $gateData */
        $gateData = json_decode((string) $gateResponse->getContent(), true);

        return ['HTTP_AUTHORIZATION' => 'Bearer ' . ($gateData['token'] ?? ''), 'HTTP_ACCEPT' => 'application/json'];
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

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'To', 'pending2@test.local');
    }

    // ── PATCH /api/admin/users/{id} ───────────────────────────────────────

    public function testUpdateUserChangesDisplayNameAndStatus(): void
    {
        $user          = UserFixtureFactory::createActiveUser(static::getContainer(), email: 'editme@test.local');
        $unlockedToken = $this->getAdminUnlockedToken();

        $this->client->request(
            'PATCH',
            '/api/admin/users/' . $user->id,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $unlockedToken,
                'HTTP_ACCEPT'        => 'application/json',
                'CONTENT_TYPE'       => 'application/json',
            ],
            (string) json_encode(['displayName' => 'Edited Name', 'status' => 'disabled']),
        );

        $data = $this->assertJsonStatus(200, $this->client->getResponse());
        $this->assertSame('Edited Name', $data['displayName']);
        $this->assertSame('disabled', $data['status']);
    }

    public function testUpdateUserWithInvalidStatusReturns422(): void
    {
        $user          = UserFixtureFactory::createActiveUser(static::getContainer(), email: 'badstatus@test.local');
        $unlockedToken = $this->getAdminUnlockedToken();

        $this->client->request(
            'PATCH',
            '/api/admin/users/' . $user->id,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $unlockedToken,
                'HTTP_ACCEPT'        => 'application/json',
                'CONTENT_TYPE'       => 'application/json',
            ],
            (string) json_encode(['status' => 'not-a-real-status']),
        );

        $this->assertJsonStatus(422, $this->client->getResponse());
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

    // ── Unknown account ───────────────────────────────────────────────────

    /** @return iterable<string, array{string, string, array<string, mixed>}> */
    public static function accountRoutes(): iterable
    {
        yield 'approve'    => ['POST', '/approve', []];
        yield 'update'     => ['PATCH', '', ['displayName' => 'Ghost']];
        yield 'delete'     => ['DELETE', '', []];
        yield 'reset link' => ['POST', '/reset-link', []];
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('accountRoutes')]
    public function testAnUnknownAccountReturns404(string $method, string $suffix, array $body): void
    {
        $headers = $this->unlockedHeaders() + ['CONTENT_TYPE' => 'application/json'];

        $this->client->request(
            $method,
            '/api/admin/users/non-existent-id' . $suffix,
            [],
            [],
            $headers,
            $body !== [] ? (string) json_encode($body) : '',
        );

        $this->assertJsonStatus(404, $this->client->getResponse());
    }

    // ── Helper ────────────────────────────────────────────────────────────

    private function getAdminUnlockedToken(): string
    {
        $gateResponse = $this->jsonRequest('POST', '/api/auth/gate', ['password' => 'test-gate-password']);
        /** @var array{token?: string} $data */
        $data = json_decode((string) $gateResponse->getContent(), true);

        return $data['token'] ?? '';
    }
}
