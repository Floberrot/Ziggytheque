<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Manga;

use App\Manga\Domain\ScanSessionAuthorizerInterface;

final class StubScanSessionAuthorizer implements ScanSessionAuthorizerInterface
{
    public function issueSubscriberToken(string $sessionId, int $ttlSeconds): string
    {
        return 'stub-scan-subscriber-token-' . $sessionId;
    }

    public function topicFor(string $sessionId): string
    {
        return 'https://ziggytheque.app/scan-session/' . $sessionId;
    }

    public function publicHubUrl(): string
    {
        return 'http://localhost:8000/.well-known/mercure';
    }
}
