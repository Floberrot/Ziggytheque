<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\RateLimit;

use App\Shared\Domain\Exception\RateLimitExceededException;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

/**
 * Fixed-window rate limiter backed by the shared cache pool — no extra dependency.
 * Used to cap expensive fan-out endpoints (edition discovery) per client so a spamming
 * client (or leaked token) cannot trigger hundreds of outbound catalogue requests.
 *
 * Fails OPEN: if the cache is unavailable, it never blocks legitimate traffic.
 */
final readonly class CacheRateLimiter
{
    public function __construct(private CacheItemPoolInterface $pool)
    {
    }

    /**
     * @throws RateLimitExceededException when more than $limit calls happen for $key
     *                                    within $windowSeconds.
     */
    public function consume(string $key, int $limit, int $windowSeconds): void
    {
        try {
            $now    = time();
            $item   = $this->pool->getItem('ratelimit.' . sha1($key));
            $cached = $item->isHit() ? $item->get() : null;

            if (
                is_array($cached)
                && isset($cached['count'], $cached['reset'])
                && is_int($cached['count'])
                && is_int($cached['reset'])
                && $cached['reset'] > $now
            ) {
                $count = $cached['count'];
                $reset = $cached['reset'];
            } else {
                $count = 0;
                $reset = $now + $windowSeconds;
            }

            if ($count >= $limit) {
                throw new RateLimitExceededException('Trop de requêtes — réessayez dans un instant.');
            }

            $item->set(['count' => $count + 1, 'reset' => $reset]);
            $item->expiresAfter(max(1, $reset - $now));
            $this->pool->save($item);
        } catch (RateLimitExceededException $exception) {
            throw $exception;
        } catch (Throwable) {
            // Cache unavailable → fail open.
        }
    }
}
