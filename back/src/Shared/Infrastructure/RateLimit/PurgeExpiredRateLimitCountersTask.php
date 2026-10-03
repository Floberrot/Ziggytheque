<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\RateLimit;

use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Daily purge of the rate-limit counters whose window has ended: every client IP or
 * email that was ever limited leaves a row behind. Runs at 04:15 UTC on the default
 * schedule provided by App\Schedule (consumed by the worker).
 */
#[AsCronTask('15 4 * * *')]
final readonly class PurgeExpiredRateLimitCountersTask
{
    public function __construct(private RateLimitCounterStoreInterface $counterStore)
    {
    }

    public function __invoke(): void
    {
        $this->counterStore->purgeExpired();
    }
}
