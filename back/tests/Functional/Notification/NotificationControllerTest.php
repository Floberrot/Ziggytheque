<?php

declare(strict_types=1);
namespace App\Tests\Functional\Notification;
use App\Tests\Functional\BaseFunctionalTest;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
class NotificationControllerTest extends BaseFunctionalTest
{
    public function testListUnreadReturns200(): void
    {
        $client = $this->createAuthenticatedClient();
        $n = new Notification('n-1', 'new_volume', 'Test notification');
        $repository = $this->createMock(NotificationRepositoryInterface::class);
        $repository->method('findUnread')->willReturn([$n]);
        static::getContainer()->set(NotificationRepositoryInterface::class, $repository);
        $client->request('GET', '/api/notifications');
        $this->assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertCount(1, $body);
        $this->assertSame('n-1', $body[0]['id']);
    }
    public function testMarkReadReturns204(): void
        $n = new Notification('n-1', 'new_volume', 'Test');
        $repository->method('findById')->with('n-1')->willReturn($n);
        $repository->expects($this->once())->method('save')->with($n);
        $client->request('PATCH', '/api/notifications/n-1/read');
        $this->assertResponseStatusCodeSame(204);
        $this->assertTrue($n->isRead);
    public function testMarkReadReturns404WhenNotFound(): void
        $repository->method('findById')->willReturn(null);
        $client->request('PATCH', '/api/notifications/missing/read');
        $this->assertResponseStatusCodeSame(404);
}
