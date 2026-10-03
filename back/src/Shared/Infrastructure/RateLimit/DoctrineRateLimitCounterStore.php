<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\RateLimit;

use DateInterval;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Psr\Clock\ClockInterface;

/**
 * Rate-limit counters in PostgreSQL, shared by every FrankenPHP thread, the worker and
 * every container. Each call is one `INSERT … ON CONFLICT DO UPDATE … RETURNING`: the
 * database serialises concurrent calls on the counter's row, so no two of them can read
 * the same count (a cache read followed by a write could let a burst through).
 */
final readonly class DoctrineRateLimitCounterStore implements RateLimitCounterStoreInterface
{
    private const string HIT_SQL = <<<'SQL'
        INSERT INTO rate_limit_counters (counter_key, hits, window_ends_at)
        VALUES (:counterKey, 1, :windowEndsAt)
        ON CONFLICT (counter_key) DO UPDATE SET
            hits = CASE
                WHEN rate_limit_counters.window_ends_at <= :now THEN 1
                ELSE rate_limit_counters.hits + 1
            END,
            window_ends_at = CASE
                WHEN rate_limit_counters.window_ends_at <= :now THEN EXCLUDED.window_ends_at
                ELSE rate_limit_counters.window_ends_at
            END
        RETURNING hits
        SQL;

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    public function hit(string $key, int $windowSeconds): int
    {
        $now = $this->clock->now();

        $hit = fn (): int => (int) $this->connection->fetchOne(
            self::HIT_SQL,
            [
                'counterKey'   => $this->counterKey($key),
                'windowEndsAt' => $now->add(new DateInterval(sprintf('PT%dS', max(1, $windowSeconds)))),
                'now'          => $now,
            ],
            [
                'windowEndsAt' => Types::DATETIME_IMMUTABLE,
                'now'          => Types::DATETIME_IMMUTABLE,
            ],
        );

        // Inside a caller's transaction, a savepoint keeps a failed upsert from aborting
        // that transaction (PostgreSQL refuses every statement after an error).
        return $this->connection->isTransactionActive() ? $this->connection->transactional($hit) : $hit();
    }

    public function forget(string $key): void
    {
        $this->connection->delete('rate_limit_counters', ['counter_key' => $this->counterKey($key)]);
    }

    public function purgeExpired(): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM rate_limit_counters WHERE window_ends_at <= :now',
            ['now' => $this->clock->now()],
            ['now' => Types::DATETIME_IMMUTABLE],
        );
    }

    /** Fixed length, and the raw key (an email, an IP) is never stored. */
    private function counterKey(string $key): string
    {
        return sha1($key);
    }
}
