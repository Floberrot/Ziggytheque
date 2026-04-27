<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain;

use App\Notification\Domain\Notification;
use PHPUnit\Framework\TestCase;

final class NotificationTest extends TestCase
{
    public function testConstruction(): void
    {
        $n = new Notification(id: 'n1', type: 'info', message: 'Hello');

        $this->assertSame('n1', $n->id);
        $this->assertSame('info', $n->type);
        $this->assertSame('Hello', $n->message);
        $this->assertFalse($n->isRead);
    }

    public function testToArray(): void
    {
        $n      = new Notification(id: 'n1', type: 'warning', message: 'Watch out', isRead: true);
        $arr    = $n->toArray();

        $this->assertSame('n1', $arr['id']);
        $this->assertSame('warning', $arr['type']);
        $this->assertSame('Watch out', $arr['message']);
        $this->assertTrue($arr['isRead']);
        $this->assertArrayHasKey('createdAt', $arr);
    }
}
