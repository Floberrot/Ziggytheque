<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\RateLimit;

use App\Shared\Infrastructure\RateLimit\RateLimitCounter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RateLimitCounterTest extends TestCase
{
    public function testHoldsTheCountOfItsWindow(): void
    {
        $windowEndsAt = new DateTimeImmutable('2026-10-03 10:01:00');

        $counter = new RateLimitCounter(sha1('login:someone@example.com'), 3, $windowEndsAt);

        $this->assertSame(sha1('login:someone@example.com'), $counter->counterKey);
        $this->assertSame(3, $counter->hits);
        $this->assertSame($windowEndsAt, $counter->windowEndsAt);
    }
}
