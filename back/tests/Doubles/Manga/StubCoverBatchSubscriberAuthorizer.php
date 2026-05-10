<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Manga;

use App\Manga\Domain\CoverBatchSubscriberAuthorizerInterface;

final class StubCoverBatchSubscriberAuthorizer implements CoverBatchSubscriberAuthorizerInterface
{
    public function issueToken(string $batchId, int $ttlSeconds): string
    {
        return 'stub-subscriber-token-' . $batchId;
    }

    public function topicFor(string $batchId): string
    {
        return 'https://ziggytheque.app/cover-batch/' . $batchId;
    }

    public function publicHubUrl(): string
    {
        return 'http://localhost:8000/.well-known/mercure';
    }
}
