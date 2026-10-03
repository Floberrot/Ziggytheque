<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Shared;

use App\Shared\Infrastructure\RateLimit\RateLimitCounterStoreInterface;

/** Counts calls per key in memory; windows never end (time is not simulated). */
final class InMemoryRateLimitCounterStore implements RateLimitCounterStoreInterface
{
    /** @var array<string, int> */
    private array $hitsByKey = [];

    public int $purgeCalls = 0;

    public function hit(string $key, int $windowSeconds): int
    {
        $this->hitsByKey[$key] = ($this->hitsByKey[$key] ?? 0) + 1;

        return $this->hitsByKey[$key];
    }

    public function forget(string $key): void
    {
        unset($this->hitsByKey[$key]);
    }

    public function purgeExpired(): int
    {
        $this->purgeCalls++;

        return 0;
    }
}
