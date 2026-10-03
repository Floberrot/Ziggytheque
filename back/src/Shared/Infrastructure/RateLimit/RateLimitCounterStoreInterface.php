<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\RateLimit;

interface RateLimitCounterStoreInterface
{
    /**
     * Counts one more call for $key in its fixed window — a new window of $windowSeconds
     * starts once the previous one has ended — and returns how many calls that window
     * holds, this one included.
     *
     * Must be atomic across processes: two concurrent calls never read the same count.
     */
    public function hit(string $key, int $windowSeconds): int;

    /** Forgets every call counted for $key. */
    public function forget(string $key): void;

    /** Deletes the counters whose window has ended; returns how many were deleted. */
    public function purgeExpired(): int;
}
