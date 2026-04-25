<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use DateTimeImmutable;

interface CrawlJobRepositoryInterface
{
    /** @param string[] $jobIds */
    public function createBatch(string $runId, array $jobIds): void;

    /**
     * Atomically marks job as done/failed, then tries to transition the run to 'finished'
     * (only succeeds if no pending jobs remain). Returns run startedAt if this handler
     * was the last one, null otherwise.
     */
    public function completeAndTryFinishRun(string $jobId, string $runId, bool $success): ?DateTimeImmutable;
}
