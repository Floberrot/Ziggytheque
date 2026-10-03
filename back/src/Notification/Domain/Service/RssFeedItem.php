<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

use DateTimeImmutable;

/** One item of an RSS feed, read once and matched against every followed series. */
final readonly class RssFeedItem
{
    public function __construct(
        /** HTML entities decoded. */
        public string $title,
        /** Tags stripped, HTML entities decoded. */
        public string $description,
        /** The item's link (its guid when it has none) — not yet checked to be a web link. */
        public string $url,
        public ?DateTimeImmutable $publishedAt,
        public ?string $imageUrl,
    ) {
    }
}
