<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain\Service;

use App\Notification\Domain\Service\RssFetchResult;
use PHPUnit\Framework\TestCase;

final class RssFetchResultTest extends TestCase
{
    public function testConstruction(): void
    {
        $result = new RssFetchResult(newCount: 3, itemsScanned: 20);

        $this->assertSame(3, $result->newCount);
        $this->assertSame(20, $result->itemsScanned);
    }

    public function testZeroes(): void
    {
        $result = new RssFetchResult(0, 0);
        $this->assertSame(0, $result->newCount);
    }
}
