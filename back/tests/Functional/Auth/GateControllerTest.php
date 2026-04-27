<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\Functional\AbstractApiTestCase;

final class GateControllerTest extends AbstractApiTestCase
{
    public function testValidPasswordReturnsToken(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/gate', ['password' => 'ziggy123'], auth: false);
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertArrayHasKey('token', $data);
        $this->assertIsString($data['token']);
        $this->assertNotEmpty($data['token']);
    }

    public function testInvalidPasswordReturns401(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/gate', ['password' => 'wrong'], auth: false);
        $this->assertJsonStatus(401, $response);
    }

    public function testMissingPasswordReturns400(): void
    {
        // No body at all → Symfony cannot parse JSON → 400 Bad Request
        $response = $this->jsonRequest('POST', '/api/auth/gate', [], auth: false);
        $this->assertJsonStatus(400, $response);
    }

    public function testEmptyPasswordReturns422(): void
    {
        // Empty string passes JSON parsing but fails @NotBlank → 422 Unprocessable Entity
        $response = $this->jsonRequest('POST', '/api/auth/gate', ['password' => ''], auth: false);
        $this->assertJsonStatus(422, $response);
    }

    public function testUnauthenticatedAccessToProtectedRoute(): void
    {
        $response = $this->jsonRequest('GET', '/api/collection', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testTokenIsUsableForProtectedRoute(): void
    {
        $response = $this->jsonRequest('GET', '/api/collection');
        $this->assertJsonStatus(200, $response);
    }
}
