<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Doctrine;

use App\Notification\Domain\CrawlJobRepositoryInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class DoctrineCrawlJobRepository implements CrawlJobRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function createBatch(string $runId, array $jobIds): void
    {
        foreach ($jobIds as $jobId) {
            $this->connection->insert('crawl_jobs', [
                'id'     => $jobId,
                'run_id' => $runId,
                'status' => 'pending',
            ]);
        }
    }

    public function completeAndTryFinishRun(string $jobId, string $runId, bool $success): ?DateTimeImmutable
    {
        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement(
                "UPDATE crawl_jobs SET status = :status, finished_at = NOW() WHERE id = :jobId AND status = 'pending'",
                ['status' => $success ? 'done' : 'failed', 'jobId' => $jobId],
            );

            $row = $this->connection->fetchAssociative(
                "UPDATE crawl_runs
                 SET status = 'finished', finished_at = NOW()
                 WHERE id = :runId
                   AND status = 'running'
                   AND NOT EXISTS (
                     SELECT 1 FROM crawl_jobs WHERE run_id = :runId AND status = 'pending'
                   )
                 RETURNING started_at",
                ['runId' => $runId],
            );

            $this->connection->commit();
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return $row !== false ? new DateTimeImmutable($row['started_at']) : null;
    }
}
