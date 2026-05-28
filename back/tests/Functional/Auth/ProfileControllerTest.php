<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\Functional\AbstractApiTestCase;

final class ProfileControllerTest extends AbstractApiTestCase
{
    // ── GET /api/me ────────────────────────────────────────────────────────

    public function testGetMeReturnsCurrentUser(): void
    {
        $response = $this->jsonRequest('GET', '/api/me');
        $data     = $this->assertJsonStatus(200, $response);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('displayName', $data);
        $this->assertSame('admin@test.local', $data['email']);
    }

    public function testGetMeRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/me', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    // ── PATCH /api/me/notifications ───────────────────────────────────────

    public function testUpdateNotificationsReturns200(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/me/notifications', [
            'channel' => 'email',
            'notificationEmail' => 'admin@test.local',
        ]);
        $this->assertJsonStatus(200, $response);
    }

    public function testUpdateNotificationsWithDiscord(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/me/notifications', [
            'channel' => 'discord',
            'discordWebhookUrl' => 'https://discord.com/api/webhooks/xxx/yyy',
        ]);
        $this->assertJsonStatus(200, $response);
    }

    public function testUpdateNotificationsRequiresChannel(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/me/notifications', []);
        $this->assertJsonStatus(400, $response);
    }

    // ── POST /api/me/notifications/test ───────────────────────────────────

    public function testTestNotificationReturns202(): void
    {
        $response = $this->jsonRequest('POST', '/api/me/notifications/test');
        $this->assertSame(202, $response->getStatusCode());
    }

    public function testTestNotificationRequiresAuth(): void
    {
        $response = $this->jsonRequest('POST', '/api/me/notifications/test', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }
}
