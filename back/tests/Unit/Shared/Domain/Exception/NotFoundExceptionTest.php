<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class NotFoundExceptionTest extends TestCase
{
    public function testMessageContainsResourceAndId(): void
    {
        $e = new NotFoundException('Manga', 'abc-123');

        $this->assertSame('Manga with id "abc-123" not found.', $e->getMessage());
    }

    public function testHttpStatusCodeIs404(): void
    {
        $e = new NotFoundException('CollectionEntry', 'xyz');

        $this->assertSame(404, $e->getHttpStatusCode());
    }
}
