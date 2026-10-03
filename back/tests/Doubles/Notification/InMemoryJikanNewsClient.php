<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Notification;

use App\Notification\Domain\Service\JikanNewsClientInterface;
use App\Notification\Domain\Service\JikanNewsItem;
use Throwable;

/** Jikan news served from memory (an unknown series has none); counts the downloads per series. */
final class InMemoryJikanNewsClient implements JikanNewsClientInterface
{
    /** @var array<string, list<JikanNewsItem>> */
    private array $newsByMalId = [];

    /** @var array<string, Throwable> */
    private array $failuresByMalId = [];

    /** @var array<string, int> */
    private array $downloadsByMalId = [];

    /** @param list<JikanNewsItem> $newsItems */
    public function serve(string $malId, array $newsItems): void
    {
        $this->newsByMalId['mal:' . $malId] = $newsItems;
    }

    public function fail(string $malId, Throwable $failure): void
    {
        $this->failuresByMalId['mal:' . $malId] = $failure;
    }

    public function downloadsOf(string $malId): int
    {
        return $this->downloadsByMalId['mal:' . $malId] ?? 0;
    }

    public function fetchNews(string $malId): array
    {
        $this->downloadsByMalId['mal:' . $malId] = $this->downloadsOf($malId) + 1;

        if (isset($this->failuresByMalId['mal:' . $malId])) {
            throw $this->failuresByMalId['mal:' . $malId];
        }

        return $this->newsByMalId['mal:' . $malId] ?? [];
    }
}
