<?php

declare(strict_types=1);

namespace App\Notification\Application\Fetch;

use App\Notification\Domain\FollowedSeries;

/** One RSS feed of one crawl run: downloaded once, matched against every followed series. */
final readonly class FetchRssFeedMessage
{
    /** @param list<FollowedSeries> $followedSeries */
    public function __construct(
        public string $feedName,
        public string $feedUrl,
        public array $followedSeries,
        public string $crawlJobId,
        public string $crawlRunId,
    ) {
    }
}
