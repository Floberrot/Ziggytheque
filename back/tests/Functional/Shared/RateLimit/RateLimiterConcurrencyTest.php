<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\RateLimit;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Several PHP processes — as FrankenPHP threads, the worker and other containers would —
 * spend one key at the same instant: exactly $limit calls get through. A read-then-write
 * counter lets two processes read the same count and both pass.
 *
 * The children commit for real (no DAMA rollback outside PHPUnit), so the key is unique
 * per run and forgotten at the end.
 */
final class RateLimiterConcurrencyTest extends KernelTestCase
{
    private const int PROCESSES = 6;
    private const int ATTEMPTS_PER_PROCESS = 15;
    private const int LIMIT = 10;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        // Builds the test container once, before the children boot their own kernel.
        self::bootKernel();
        $this->key = 'concurrency-test:' . Uuid::v4()->toRfc4122();
    }

    protected function tearDown(): void
    {
        $this->runChild(['reset', $this->key]);
        parent::tearDown();
    }

    public function testConcurrentProcessesNeverPassTheLimit(): void
    {
        $startAt   = sprintf('%.6F', microtime(true) + 1.5);
        $processes = [];

        for ($child = 0; $child < self::PROCESSES; $child++) {
            $processes[] = $this->startChild([
                'consume',
                $this->key,
                (string) self::LIMIT,
                (string) self::ATTEMPTS_PER_PROCESS,
                $startAt,
            ]);
        }

        $allowed = 0;
        foreach ($processes as [$process, $output]) {
            $printed = (string) stream_get_contents($output);
            fclose($output);
            $this->assertSame(0, proc_close($process), 'A child process failed: ' . $printed);
            $this->assertMatchesRegularExpression('/^\d+$/', $printed, 'Unexpected child output: ' . $printed);
            $allowed += (int) $printed;
        }

        $this->assertSame(self::LIMIT, $allowed);
    }

    /** @param list<string> $arguments */
    private function runChild(array $arguments): void
    {
        [$process, $output] = $this->startChild($arguments);
        stream_get_contents($output);
        fclose($output);
        proc_close($process);
    }

    /**
     * @param  list<string> $arguments
     * @return array{resource, resource}
     */
    private function startChild(array $arguments): array
    {
        $command = [PHP_BINARY, __DIR__ . '/Fixtures/consume-rate-limit.php', ...$arguments];
        $environment = [...getenv(), 'APP_ENV' => 'test'];

        // stderr joins stdout: a child's warning shows in the failure message.
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, null, $environment);
        $this->assertIsResource($process);

        return [$process, $pipes[1]];
    }
}
