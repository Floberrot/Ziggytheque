<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\RateLimit;

use App\Shared\Domain\Exception\RateLimitExceededException;
use App\Shared\Infrastructure\RateLimit\CacheRateLimiter;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CacheRateLimiterTest extends TestCase
{
    public function testAllowsUpToTheLimit(): void
    {
        $limiter = new CacheRateLimiter(new ArrayAdapter());

        for ($call = 0; $call < 5; $call++) {
            $limiter->consume('client-a', 5, 60);
        }

        $this->addToAssertionCount(1); // no exception within the limit
    }

    public function testThrowsOnceTheLimitIsExceeded(): void
    {
        $limiter = new CacheRateLimiter(new ArrayAdapter());
        for ($call = 0; $call < 3; $call++) {
            $limiter->consume('client-b', 3, 60);
        }

        $this->expectException(RateLimitExceededException::class);
        $limiter->consume('client-b', 3, 60);
    }

    public function testDifferentKeysAreCountedIndependently(): void
    {
        $limiter = new CacheRateLimiter(new ArrayAdapter());

        $limiter->consume('client-c', 1, 60);
        $limiter->consume('client-d', 1, 60); // separate bucket, still allowed

        $this->addToAssertionCount(1);
    }

    public function testResetStartsTheCountOver(): void
    {
        $limiter = new CacheRateLimiter(new ArrayAdapter());
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
        $limiter = new CacheRateLimiter(new ArrayAdapter());
        $limiter->consume('client-g', 1, 60);
        $limiter->consume('client-h', 1, 60);

        $limiter->reset('client-h');

        $this->expectException(RateLimitExceededException::class);
        $limiter->consume('client-g', 1, 60);
    }

    public function testResetIgnoresACacheOutage(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('deleteItem')->willThrowException(new RuntimeException('cache down'));

        (new CacheRateLimiter($pool))->reset('client-i');

        $this->addToAssertionCount(1);
    }

    public function testFailsOpenWhenCacheThrows(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willThrowException(new RuntimeException('cache down'));

        // A cache outage must never block a request.
        (new CacheRateLimiter($pool))->consume('client-e', 1, 60);

        $this->addToAssertionCount(1);
    }
}
