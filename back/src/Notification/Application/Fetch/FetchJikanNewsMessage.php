<?php

declare(strict_types=1);

namespace App\Notification\Application\Fetch;

final readonly class FetchJikanNewsMessage
{
    public function __construct(
        public string $collectionEntryId,
        public string $mangaTitle,
        public string $malId,
        public string $crawlJobId,
        public string $crawlRunId,
    ) {
    }
}
