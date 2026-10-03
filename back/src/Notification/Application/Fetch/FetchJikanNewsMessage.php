<?php

declare(strict_types=1);

namespace App\Notification\Application\Fetch;

use App\Notification\Domain\FollowedSeries;

/**
 * The Jikan news of one MyAnimeList series for one crawl run: downloaded once, matched
 * against every followed copy of that series (one per account that follows it).
 */
final readonly class FetchJikanNewsMessage
{
    /** @param list<FollowedSeries> $followedSeries */
    public function __construct(
        public string $malId,
        public array $followedSeries,
        public string $crawlJobId,
        public string $crawlRunId,
    ) {
    }
}
