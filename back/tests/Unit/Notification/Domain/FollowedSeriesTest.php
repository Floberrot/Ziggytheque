<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain;

use App\Notification\Domain\FollowedSeries;
use PHPUnit\Framework\TestCase;

final class FollowedSeriesTest extends TestCase
{
    public function testHoldsTheEntryAndTheTitleToLookFor(): void
    {
        $followedSeries = new FollowedSeries('entry-1', 'One Piece');

        $this->assertSame('entry-1', $followedSeries->collectionEntryId);
        $this->assertSame('One Piece', $followedSeries->mangaTitle);
    }

    public function testSurvivesTheMessengerSerialisation(): void
    {
        $followedSeries = new FollowedSeries('entry-1', 'Berserk');

        $this->assertEquals($followedSeries, unserialize(serialize($followedSeries)));
    }
}
