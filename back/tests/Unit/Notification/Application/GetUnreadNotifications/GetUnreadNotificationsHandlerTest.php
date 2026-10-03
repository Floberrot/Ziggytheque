<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application\GetUnreadNotifications;

use App\Notification\Application\GetUnreadNotifications\GetUnreadNotificationsHandler;
use App\Notification\Application\GetUnreadNotifications\GetUnreadNotificationsQuery;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class GetUnreadNotificationsHandlerTest extends TestCase
{
    public function testReturnsTheUnreadNotificationsAsArraysInRepositoryOrder(): void
    {
        $newest = new Notification(id: 'n-2', type: 'test_failure', message: 'Newest');
        $oldest = new Notification(id: 'n-1', type: 'info', message: 'Oldest');

        $repository = $this->createStub(NotificationRepositoryInterface::class);
        $repository->method('findUnread')->willReturn([$newest, $oldest]);

        $result = (new GetUnreadNotificationsHandler($repository))(new GetUnreadNotificationsQuery());

        $this->assertSame(['n-2', 'n-1'], array_column($result, 'id'));
        $this->assertSame($newest->toArray(), $result[0]);
    }

    public function testReturnsAnEmptyListWhenEverythingIsRead(): void
    {
        $repository = $this->createStub(NotificationRepositoryInterface::class);
        $repository->method('findUnread')->willReturn([]);

        $this->assertSame([], (new GetUnreadNotificationsHandler($repository))(new GetUnreadNotificationsQuery()));
    }
}
