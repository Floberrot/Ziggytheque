<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

use App\Collection\Domain\CollectionEntry;
use App\Notification\Domain\FollowedSeries;

/**
 * Plans the daily news crawl so each source is downloaded once per run: every RSS feed
 * is matched against all the followed series, and each MyAnimeList series — which
 * several accounts may follow, each through its own copy — is asked for its news once.
 */
final readonly class CrawlPlanner
{
    /**
     * @param  iterable<CollectionEntry> $followedEntries
     * @return list<FollowedSeries>
     */
    public function followedSeries(iterable $followedEntries): array
    {
        $followedSeries = [];
        foreach ($followedEntries as $entry) {
            $followedSeries[] = new FollowedSeries($entry->id, $entry->manga->title);
        }

        return $followedSeries;
    }

    /**
     * The followed series linked to a MyAnimeList series, grouped by its id (first seen
     * first); series without one have no Jikan news.
     *
     * @param  iterable<CollectionEntry> $followedEntries
     * @return list<array{malId: string, followedSeries: list<FollowedSeries>}>
     */
    public function followedSeriesByMalId(iterable $followedEntries): array
    {
        $groups = [];
        foreach ($followedEntries as $entry) {
            $malId = $entry->manga->externalId;
            if ($malId === null) {
                continue;
            }

            // Prefixed: a numeric id used as a PHP array key would turn into an int.
            $groupKey = 'mal:' . $malId;
            $groups[$groupKey] ??= ['malId' => $malId, 'followedSeries' => []];
            $groups[$groupKey]['followedSeries'][] = new FollowedSeries($entry->id, $entry->manga->title);
        }

        return array_values($groups);
    }
}
