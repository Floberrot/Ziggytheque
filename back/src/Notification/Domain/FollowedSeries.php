<?php

declare(strict_types=1);

namespace App\Notification\Domain;

/** A followed collection entry as the daily crawl plans it: whose news to look for. */
final readonly class FollowedSeries
{
    public function __construct(
        public string $collectionEntryId,
        public string $mangaTitle,
    ) {
    }
}
