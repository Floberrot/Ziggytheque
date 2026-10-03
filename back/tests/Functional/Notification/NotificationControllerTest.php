<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification;

use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Notification\Domain\Notification;
use App\Tests\Functional\AbstractApiTestCase;
use App\Tests\Functional\Fixtures\UserFixtureFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class NotificationControllerTest extends AbstractApiTestCase
{
    private function createNotification(
        string $message = 'Test notification',
        bool $isRead = false,
        ?User $owner = null,
        ?DateTimeImmutable $createdAt = null,
    ): string {
        $container = static::getContainer();
        /** @var UserRepositoryInterface $users */
        $users = $container->get(UserRepositoryInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        $notification = new Notification(
            id: Uuid::v4()->toRfc4122(),
            type: 'info',
            message: $message,
            owner: $owner ?? $users->findByEmail('admin@test.local'),
            isRead: $isRead,
        );
        if ($createdAt !== null) {
            $notification->createdAt = $createdAt;
        }
        $entityManager->persist($notification);
        $entityManager->flush();

        return $notification->id;
    }

    /** @return list<string> */
    private function listedIds(): array
    {
        $data = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/notifications'));

        return array_column($data, 'id');
    }

    // ── GET /api/notifications ───────────────────────────────────────────────

    public function testListRequiresAuth(): void
    {
        $response = $this->jsonRequest('GET', '/api/notifications', auth: false);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testListIsEmptyWithoutNotifications(): void
    {
        $this->assertSame([], $this->listedIds());
    }

    public function testListReturnsUnreadNotificationsNewestFirst(): void
    {
        $older = $this->createNotification('Older', createdAt: new DateTimeImmutable('-2 hours'));
        $newer = $this->createNotification('Newer', createdAt: new DateTimeImmutable('-1 hour'));

        $data = $this->assertJsonStatus(200, $this->jsonRequest('GET', '/api/notifications'));

        $this->assertSame([$newer, $older], array_column($data, 'id'));
        $this->assertSame('Newer', $data[0]['message']);
        $this->assertFalse($data[0]['isRead']);
        $this->assertArrayHasKey('createdAt', $data[0]);
    }

    public function testListExcludesReadNotifications(): void
    {
        $unread = $this->createNotification('Unread');
        $read   = $this->createNotification('Read', isRead: true);

        $ids = $this->listedIds();

        $this->assertContains($unread, $ids);
        $this->assertNotContains($read, $ids);
    }

    public function testListNeverShowsAnotherAccountsNotifications(): void
    {
        $someoneElse = UserFixtureFactory::createActiveUser(static::getContainer(), email: 'other@test.local');
        $theirs      = $this->createNotification('Theirs', owner: $someoneElse);
        $mine        = $this->createNotification('Mine');

        $this->assertSame([$mine], $this->listedIds());
        $this->assertNotContains($theirs, $this->listedIds());
    }
}
