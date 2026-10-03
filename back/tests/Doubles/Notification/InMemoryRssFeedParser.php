<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Notification;

use App\Notification\Domain\Service\RssFeedItem;
use App\Notification\Domain\Service\RssFeedParserInterface;
use Throwable;

/** Feeds served from memory (an unknown feed is empty); counts the downloads per feed. */
final class InMemoryRssFeedParser implements RssFeedParserInterface
{
    /** @var array<string, list<RssFeedItem>> */
    private array $itemsByUrl = [];

    /** @var array<string, Throwable> */
    private array $failuresByUrl = [];

    /** @var array<string, int> */
    private array $downloadsByUrl = [];

    /** @param list<RssFeedItem> $items */
    public function serve(string $feedUrl, array $items): void
    {
        $this->itemsByUrl[$feedUrl] = $items;
    }

    public function fail(string $feedUrl, Throwable $failure): void
    {
        $this->failuresByUrl[$feedUrl] = $failure;
    }

    public function downloadsOf(string $feedUrl): int
    {
        return $this->downloadsByUrl[$feedUrl] ?? 0;
    }

    public function parse(string $feedUrl): array
    {
        $this->downloadsByUrl[$feedUrl] = $this->downloadsOf($feedUrl) + 1;

        if (isset($this->failuresByUrl[$feedUrl])) {
            throw $this->failuresByUrl[$feedUrl];
        }

        return $this->itemsByUrl[$feedUrl] ?? [];
    }
}
