<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\RateLimit;

use App\Shared\Infrastructure\RateLimit\PurgeExpiredRateLimitCountersTask;
use App\Tests\Doubles\Shared\InMemoryRateLimitCounterStore;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

final class PurgeExpiredRateLimitCountersTaskTest extends TestCase
{
    public function testPurgesTheExpiredCounters(): void
    {
        $store = new InMemoryRateLimitCounterStore();

        (new PurgeExpiredRateLimitCountersTask($store))();

        $this->assertSame(1, $store->purgeCalls);
    }

    public function testRunsDaily(): void
    {
        $attributes = (new ReflectionClass(PurgeExpiredRateLimitCountersTask::class))
            ->getAttributes(AsCronTask::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('15 4 * * *', $attributes[0]->newInstance()->expression);
    }
}
