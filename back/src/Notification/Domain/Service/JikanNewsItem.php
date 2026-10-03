<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

/** One news item Jikan (MyAnimeList) lists for a series, as received. */
final readonly class JikanNewsItem
{
    public function __construct(
        /** Not yet checked to be a web link; null when Jikan sent none. */
        public ?string $url,
        public string $title,
        public string $excerpt,
        public ?string $author,
        /** As Jikan writes it (ISO 8601); read only for the news that match. */
        public ?string $date,
    ) {
    }
}
