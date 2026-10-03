<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\RateLimit;

use App\Shared\Domain\Exception\RateLimitExceededException;
use App\Shared\Infrastructure\RateLimit\CacheRateLimiter;
use App\Shared\Infrastructure\RateLimit\RateLimitCounterStoreInterface;
use App\Tests\Doubles\Shared\InMemoryRateLimitCounterStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CacheRateLimiterTest extends TestCase
{
    public function testAllowsUpToTheLimit(): void
    {
        $limiter = new CacheRateLimiter(new InMemoryRateLimitCounterStore());

        for ($call = 0; $call < 5; $call++) {
            $limiter->consume('client-a', 5, 60);
        }

        $this->addToAssertionCount(1); // no exception within the limit
    }

    public function testThrowsOnceTheLimitIsExceeded(): void
    {
        $limiter = new CacheRateLimiter(new InMemoryRateLimitCounterStore());
        for ($call = 0; $call < 3; $call++) {
            $limiter->consume('client-b', 3, 60);
        }

        $this->expectException(RateLimitExceededException::class);
        $limiter->consume('client-b', 3, 60);
    }

    public function testKeepsRefusingWhileTheWindowLasts(): void
    {
        $limiter = new CacheRateLimiter(new InMemoryRateLimitCounterStore());
        $limiter->consume('client-j', 1, 60);

        $refusals = 0;
        for ($call = 0; $call < 3; $call++) {
            try {
                $limiter->consume('client-j', 1, 60);
            } catch (RateLimitExceededException) {
                $refusals++;
            }
        }

        $this->assertSame(3, $refusals);
    }

    public function testPassesTheKeyAndTheWindowToTheStore(): void
    {
        $store = $this->createMock(RateLimitCounterStoreInterface::class);
        $store->expects($this->once())->method('hit')->with('client-k', 300)->willReturn(1);

        (new CacheRateLimiter($store))->consume('client-k', 10, 300);
    }

    public function testDifferentKeysAreCountedIndependently(): void
    {
        $limiter = new CacheRateLimiter(new InMemoryRateLimitCounterStore());

        $limiter->consume('client-c', 1, 60);
        $limiter->consume('client-d', 1, 60); // separate bucket, still allowed

        $this->addToAssertionCount(1);
    }

    public function testResetStartsTheCountOver(): void
    {
        $limiter = new CacheRateLimiter(new InMemoryRateLimitCounterStore());
        $limiter->consume('client-f', 2, 60);
        $limiter->consume('client-f', 2, 60);

        $limiter->reset('client-f');

        $limiter->consume('client-f', 2, 60);
        $limiter->consume('client-f', 2, 60);
        $this->expectException(RateLimitExceededException::class);
        $limiter->consume('client-f', 2, 60);
    }

    public function testResetLeavesOtherKeysAlone(): void
    {
        $limiter = new CacheRateLimiter(new InMemoryRateLimitCounterStore());
        $limiter->consume('client-g', 1, 60);
        $limiter->consume('client-h', 1, 60);

        $limiter->reset('client-h');

        $this->expectException(RateLimitExceededException::class);
        $limiter->consume('client-g', 1, 60);
    }

    public function testResetIgnoresAStorageOutage(): void
    {
        $store = $this->createStub(RateLimitCounterStoreInterface::class);
        $store->method('forget')->willThrowException(new RuntimeException('database down'));

        (new CacheRateLimiter($store))->reset('client-i');

        $this->addToAssertionCount(1);
    }

    public function testFailsOpenWhenTheStorageThrows(): void
    {
        $store = $this->createStub(RateLimitCounterStoreInterface::class);
        $store->method('hit')->willThrowException(new RuntimeException('database down'));

        // A storage outage must never block a request.
        (new CacheRateLimiter($store))->consume('client-e', 1, 60);

        $this->addToAssertionCount(1);
    }
}
