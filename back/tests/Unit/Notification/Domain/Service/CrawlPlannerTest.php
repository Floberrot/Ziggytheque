<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain\Service;

use App\Notification\Domain\FollowedSeries;
use App\Notification\Domain\Service\CrawlPlanner;
use App\Tests\Doubles\Notification\FollowedEntryFactory;
use PHPUnit\Framework\TestCase;

final class CrawlPlannerTest extends TestCase
{
    private CrawlPlanner $planner;

    protected function setUp(): void
    {
        $this->planner = new CrawlPlanner();
    }

    public function testEveryFollowedEntryIsLookedForUnderItsTitle(): void
    {
        $followedSeries = $this->planner->followedSeries([
            FollowedEntryFactory::entry('entry-a', 'One Piece', '13'),
            FollowedEntryFactory::entry('entry-b', 'Berserk'),
        ]);

        $this->assertEquals(
            [new FollowedSeries('entry-a', 'One Piece'), new FollowedSeries('entry-b', 'Berserk')],
            $followedSeries,
        );
    }

    public function testNothingFollowedMeansNothingToLookFor(): void
    {
        $this->assertSame([], $this->planner->followedSeries([]));
        $this->assertSame([], $this->planner->followedSeriesByMalId([]));
    }

    /** Two accounts following their own copy of a series: its Jikan news are fetched once. */
    public function testGroupsTheCopiesOfAMyAnimeListSeriesFirstSeenFirst(): void
    {
        $groups = $this->planner->followedSeriesByMalId([
            FollowedEntryFactory::entry('entry-a', 'One Piece', '13'),
            FollowedEntryFactory::entry('entry-b', 'Naruto', '11'),
            FollowedEntryFactory::entry('entry-c', 'One Piece', '13'),
        ]);

        $this->assertEquals(
            [
                ['malId' => '13', 'followedSeries' => [
                    new FollowedSeries('entry-a', 'One Piece'),
                    new FollowedSeries('entry-c', 'One Piece'),
                ]],
                ['malId' => '11', 'followedSeries' => [new FollowedSeries('entry-b', 'Naruto')]],
            ],
            $groups,
        );
        // Numeric ids stay strings (a PHP array key would have turned them into ints).
        $this->assertSame('13', $groups[0]['malId']);
    }

    public function testASeriesWithoutMyAnimeListIdHasNoJikanNews(): void
    {
        $groups = $this->planner->followedSeriesByMalId([
            FollowedEntryFactory::entry('entry-a', 'Berserk'),
        ]);

        $this->assertSame([], $groups);
    }
}
