<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain\Service;

use App\Notification\Domain\Service\JikanFetchResult;
use PHPUnit\Framework\TestCase;

final class JikanFetchResultTest extends TestCase
{
    public function testConstruction(): void
    {
        $result = new JikanFetchResult(newCount: 7, itemsReceived: 25);

        $this->assertSame(7, $result->newCount);
        $this->assertSame(25, $result->itemsReceived);
    }
}
