<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\RateLimit;

use App\Shared\Domain\Exception\RateLimitExceededException;
use Throwable;

/**
 * Fixed-window rate limiter — no extra dependency. Caps expensive or sensitive endpoints
 * per client (account, IP, email) so a spamming client (or a leaked token) cannot trigger
 * hundreds of outbound requests or guess passwords.
 *
 * The counters live in PostgreSQL ({@see DoctrineRateLimitCounterStore}): every call is
 * counted atomically, so a burst of concurrent requests cannot slip past the limit, and
 * the web container and the worker share them.
 *
 * Fails OPEN: if the database is unavailable, it never blocks legitimate traffic.
 */
final readonly class CacheRateLimiter
{
    public function __construct(private RateLimitCounterStoreInterface $counterStore)
    {
    }

    /**
     * @throws RateLimitExceededException when more than $limit calls happen for $key
     *                                    within $windowSeconds.
     */
    public function consume(string $key, int $limit, int $windowSeconds): void
    {
        try {
            $calls = $this->counterStore->hit($key, $windowSeconds);
        } catch (Throwable) {
            // Storage unavailable → fail open.
            return;
        }

        if ($calls > $limit) {
            throw new RateLimitExceededException('Trop de requêtes — réessayez dans un instant.');
        }
    }

    /** Forgets the calls counted for $key (a successful login clears its failed attempts). */
    public function reset(string $key): void
    {
        try {
            $this->counterStore->forget($key);
        } catch (Throwable) {
            // Storage unavailable: nothing was blocking anyway (fail open).
        }
    }
}
