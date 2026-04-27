<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification;

use App\Notification\Domain\Notification;
use App\Tests\Functional\AbstractApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationControllerTest extends AbstractApiTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var EntityManagerInterface $em */
        $em       = static::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;
    }

    private function createNotification(string $type = 'info', string $message = 'Test notification'): string
    {
        $n = new Notification(id: 'notif-' . uniqid(), type: $type, message: $message);
        $this->em->persist($n);
        $this->em->flush();
        return $n->id;
    }

    // ── GET /api/notifications ───────────────────────────────────────────────

    public function testListRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/notifications', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testListReturnsArray(): void
    {
        $response = $this->jsonRequest('GET', '/api/notifications');
        $data     = $this->assertJsonStatus(200, $response);
        $this->assertIsArray($data);
    }

    public function testListReturnsUnreadNotifications(): void
    {
        $id = $this->createNotification(message: 'Unread message');

        $response = $this->jsonRequest('GET', '/api/notifications');
        $data     = $this->assertJsonStatus(200, $response);

        $ids = array_column($data, 'id');
        $this->assertContains($id, $ids);
    }

    public function testListExcludesReadNotifications(): void
    {
        $id = $this->createNotification(message: 'Read message');

        // Mark it as read
        $this->jsonRequest('PATCH', '/api/notifications/' . $id . '/read');

        $response = $this->jsonRequest('GET', '/api/notifications');
        $data     = $this->assertJsonStatus(200, $response);

        $ids = array_column($data, 'id');
        $this->assertNotContains($id, $ids);
    }

    // ── PATCH /api/notifications/{id}/read ──────────────────────────────────

    public function testMarkRead(): void
    {
        $id = $this->createNotification();

        $response = $this->jsonRequest('PATCH', '/api/notifications/' . $id . '/read');
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testMarkReadNotFoundReturns404(): void
    {
        $response = $this->jsonRequest('PATCH', '/api/notifications/nonexistent/read');
        $this->assertJsonStatus(404, $response);
    }

    public function testMarkReadRequiresAuth(): void
    {
        $id = $this->createNotification();

        $response = $this->jsonRequest('PATCH', '/api/notifications/' . $id . '/read', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }
}
