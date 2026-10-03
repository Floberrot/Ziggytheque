<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\RateLimit;

use App\Shared\Domain\Exception\RateLimitExceededException;
use App\Shared\Infrastructure\RateLimit\CacheRateLimiter;
use App\Shared\Infrastructure\RateLimit\DoctrineRateLimitCounterStore;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/** Runs against the real PostgreSQL table (DAMA rolls every test back). */
final class DoctrineRateLimitCounterStoreTest extends KernelTestCase
{
    private Connection $connection;
    private MockClock $clock;
    private DoctrineRateLimitCounterStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        /** @var Connection $connection */
        $connection       = static::getContainer()->get(Connection::class);
        $this->connection = $connection;
        $this->clock      = new MockClock('2026-10-03 10:00:00');
        $this->store      = new DoctrineRateLimitCounterStore($this->connection, $this->clock);
    }

    public function testCountsEveryCallOfTheWindow(): void
    {
        $this->assertSame(1, $this->store->hit('client-a', 60));
        $this->assertSame(2, $this->store->hit('client-a', 60));
        $this->assertSame(3, $this->store->hit('client-a', 60));
    }

    public function testCountsEachKeyApart(): void
    {
        $this->store->hit('client-b', 60);
        $this->store->hit('client-b', 60);

        $this->assertSame(1, $this->store->hit('client-c', 60));
    }

    public function testTheWindowIsFixed(): void
    {
        $this->store->hit('client-d', 60);
        $this->clock->sleep(59);

        // Still the first window: it does not slide with each call.
        $this->assertSame(2, $this->store->hit('client-d', 60));
    }

    public function testANewWindowStartsOnceTheLastOneEnded(): void
    {
        $this->store->hit('client-e', 60);
        $this->store->hit('client-e', 60);
        $this->clock->sleep(60);

        $this->assertSame(1, $this->store->hit('client-e', 60));
        $this->assertSame(2, $this->store->hit('client-e', 60));
    }

    public function testStoresOnlyAHashOfTheKey(): void
    {
        $this->store->hit('login:someone@example.com', 60);

        $storedKeys = $this->connection->fetchFirstColumn('SELECT counter_key FROM rate_limit_counters');

        $this->assertContains(sha1('login:someone@example.com'), $storedKeys);
        $this->assertNotContains('login:someone@example.com', $storedKeys);
    }

    public function testForgetStartsTheCountOver(): void
    {
        $this->store->hit('client-f', 60);
        $this->store->hit('client-f', 60);

        $this->store->forget('client-f');

        $this->assertSame(1, $this->store->hit('client-f', 60));
    }

    public function testPurgeDeletesOnlyTheEndedWindows(): void
    {
        $this->store->hit('client-short', 60);
        $this->store->hit('client-long', 3600);
        $this->clock->sleep(120);

        $this->assertGreaterThanOrEqual(1, $this->store->purgeExpired());

        $storedKeys = $this->connection->fetchFirstColumn('SELECT counter_key FROM rate_limit_counters');
        $this->assertNotContains(sha1('client-short'), $storedKeys);
        $this->assertContains(sha1('client-long'), $storedKeys);
    }

    public function testTheLimiterRefusesTheCallPastTheLimit(): void
    {
        $limiter = new CacheRateLimiter($this->store);
        $limiter->consume('client-g', 2, 60);
        $limiter->consume('client-g', 2, 60);

        $this->expectException(RateLimitExceededException::class);
        $limiter->consume('client-g', 2, 60);
    }

    /**
     * Inside a caller's transaction, a failing upsert is rolled back to its own savepoint:
     * the limiter fails open and the caller's transaction stays usable.
     */
    public function testAFailureFailsOpenWithoutBreakingTheCallersTransaction(): void
    {
        $this->connection->beginTransaction();
        $this->connection->executeStatement('ALTER TABLE rate_limit_counters RENAME TO rate_limit_counters_away');

        (new CacheRateLimiter($this->store))->consume('client-h', 1, 60);

        $this->assertSame(1, (int) $this->connection->fetchOne('SELECT 1'));
        $this->connection->rollBack();
    }

    public function testAHitInsideACallersTransactionIsCounted(): void
    {
        $this->connection->beginTransaction();

        $this->assertSame(1, $this->store->hit('client-i', 60));
        $this->assertSame(2, $this->store->hit('client-i', 60));

        $this->connection->commit();
        $this->assertSame(3, $this->store->hit('client-i', 60));
    }
}
