<?php

declare(strict_types=1);

namespace App\Notification\Application\Fetch;

final readonly class FetchRssFeedMessage
{
    public function __construct(
        public string $collectionEntryId,
        public string $mangaTitle,
        public string $feedName,
        public string $feedUrl,
        public string $crawlJobId,
        public string $crawlRunId,
    ) {
    }
}
