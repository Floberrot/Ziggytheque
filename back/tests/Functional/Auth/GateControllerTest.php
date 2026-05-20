<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\Functional\AbstractApiTestCase;
use App\Tests\Functional\Fixtures\UserFixtureFactory;

/**
 * Gate is now a second-factor for admins:
 *  1. Login as admin → ROLE_ADMIN JWT
 *  2. POST /api/auth/gate with that JWT + gate password → ROLE_ADMIN_UNLOCKED JWT
 */
final class GateControllerTest extends AbstractApiTestCase
{
    public function testValidPasswordReturnsAdminUnlockedToken(): void
    {
        // $this->token is already a ROLE_ADMIN JWT (set up in AbstractApiTestCase::setUp)
        $response = $this->jsonRequest('POST', '/api/auth/gate', ['password' => 'ziggy123']);
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertArrayHasKey('token', $data);
        $this->assertIsString($data['token']);
        $this->assertNotEmpty($data['token']);
    }

    public function testInvalidPasswordReturns401(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/gate', ['password' => 'wrong']);
        $this->assertJsonStatus(401, $response);
    }

    public function testMissingPasswordReturns400(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/gate', []);
        $this->assertJsonStatus(400, $response);
    }

    public function testEmptyPasswordReturns422(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/gate', ['password' => '']);
        $this->assertJsonStatus(422, $response);
    }

    public function testGateRequiresAuthentication(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/gate', ['password' => 'ziggy123'], auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testNonAdminCannotAccessGate(): void
    {
        $regularUser = UserFixtureFactory::createActiveUser(
            static::getContainer(),
            email: 'regular@test.local',
        );

        // Log in as regular user
        $loginResponse = $this->jsonRequest(
            'POST',
            '/api/auth/login',
            ['email' => $regularUser->email, 'password' => 'Test1234!'],
            auth: false,
        );
        /** @var array{token?: string} $loginData */
        $loginData  = json_decode((string) $loginResponse->getContent(), true);
        $userToken  = $loginData['token'] ?? '';

        $this->client->request(
            'POST',
            '/api/auth/gate',
            [],
            [],
            [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $userToken,
            ],
            (string) json_encode(['password' => 'ziggy123']),
        );

        $this->assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testTokenIsUsableForProtectedRoute(): void
    {
        $response = $this->jsonRequest('GET', '/api/collection');
        $this->assertJsonStatus(200, $response);
    }

    public function testUnauthenticatedAccessToProtectedRoute(): void
    {
        $response = $this->jsonRequest('GET', '/api/collection', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }
}
