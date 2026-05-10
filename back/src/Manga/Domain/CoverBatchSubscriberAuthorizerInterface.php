<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface CoverBatchSubscriberAuthorizerInterface
{
    public function issueToken(string $batchId, int $ttlSeconds): string;

    public function topicFor(string $batchId): string;

    public function publicHubUrl(): string;
}
