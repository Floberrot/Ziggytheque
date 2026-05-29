<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface ScanSessionAuthorizerInterface
{
    public function issueSubscriberToken(string $sessionId, int $ttlSeconds): string;

    public function topicFor(string $sessionId): string;

    public function publicHubUrl(): string;
}
