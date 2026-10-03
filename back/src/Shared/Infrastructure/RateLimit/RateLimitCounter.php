<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\RateLimit;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * One fixed-window rate-limit counter. Mapped only so the schema (and its migration)
 * stays under Doctrine's control: rows are read and written by
 * {@see DoctrineRateLimitCounterStore} with a single atomic upsert, never through the ORM.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'rate_limit_counters')]
// The daily purge deletes by window end.
#[ORM\Index(columns: ['window_ends_at'])]
final class RateLimitCounter
{
    public function __construct(
        /** sha1 of the limited key (an account, an IP, an email…): fixed length, nothing personal stored. */
        #[ORM\Id]
        #[ORM\Column(length: 40)]
        public readonly string $counterKey,
        #[ORM\Column]
        public readonly int $hits,
        #[ORM\Column]
        public readonly DateTimeImmutable $windowEndsAt,
    ) {
    }
}
