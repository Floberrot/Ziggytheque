<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain;

use App\Notification\Domain\Notification;
use PHPUnit\Framework\TestCase;

class NotificationTest extends TestCase
{
    public function testConstructorSetsFields(): void
    {
        $n = new Notification('n-1', 'new_volume', 'Naruto tome 73 is available');

        $this->assertSame('n-1', $n->id);
        $this->assertSame('new_volume', $n->type);
        $this->assertSame('Naruto tome 73 is available', $n->message);
        $this->assertFalse($n->isRead);
        $this->assertInstanceOf(\DateTimeImmutable::class, $n->createdAt);
    }

    public function testToArrayContainsExpectedKeys(): void
    {
        $n = new Notification('n-1', 'new_volume', 'Test message');
        $arr = $n->toArray();

        $this->assertArrayHasKey('id', $arr);
        $this->assertArrayHasKey('type', $arr);
        $this->assertArrayHasKey('message', $arr);
        $this->assertArrayHasKey('isRead', $arr);
        $this->assertArrayHasKey('createdAt', $arr);
        $this->assertSame('n-1', $arr['id']);
        $this->assertFalse($arr['isRead']);
    }

    public function testMarkingAsRead(): void
    {
        $n = new Notification('n-1', 'info', 'Test');
        $n->isRead = true;

        $this->assertTrue($n->isRead);
    }
}
