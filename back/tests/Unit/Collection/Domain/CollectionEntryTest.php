<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Domain;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\ReadingStatusEnum;
use App\Collection\Domain\VolumeEntry;
use App\Manga\Domain\Manga;
use App\Manga\Domain\Volume;
use PHPUnit\Framework\TestCase;

class CollectionEntryTest extends TestCase
{
    private Manga $manga;

    protected function setUp(): void
    {
        $this->manga = new Manga('m-1', 'Test Manga', 'Ed', 'fr');
    }

    public function testDefaultReadingStatusIsNotStarted(): void
    {
        $entry = new CollectionEntry('ce-1', $this->manga);

        $this->assertSame(ReadingStatusEnum::NotStarted, $entry->readingStatus);
        $this->assertCount(0, $entry->volumeEntries);
        $this->assertInstanceOf(\DateTimeImmutable::class, $entry->addedAt);
    }

    public function testToArrayCountsOwnedReadAndWished(): void
    {
        $entry = new CollectionEntry('ce-1', $this->manga);
        $v1 = new Volume('v-1', $this->manga, 1);
        $v2 = new Volume('v-2', $this->manga, 2);
        $v3 = new Volume('v-3', $this->manga, 3);

        $ve1 = new VolumeEntry('ve-1', $entry, $v1, isOwned: true, isRead: true);
        $ve2 = new VolumeEntry('ve-2', $entry, $v2, isOwned: true, isRead: false);
        $ve3 = new VolumeEntry('ve-3', $entry, $v3, isOwned: false, isWished: true);

        $entry->volumeEntries->add($ve1);
        $entry->volumeEntries->add($ve2);
        $entry->volumeEntries->add($ve3);

        $arr = $entry->toArray();

        $this->assertSame(2, $arr['ownedCount']);
        $this->assertSame(1, $arr['readCount']);
        $this->assertSame(1, $arr['wishedCount']);
    }

    public function testToDetailArraySortsVolumesByNumber(): void
    {
        $entry = new CollectionEntry('ce-1', $this->manga);
        $v3 = new Volume('v-3', $this->manga, 3);
        $v1 = new Volume('v-1', $this->manga, 1);
        $v2 = new Volume('v-2', $this->manga, 2);

        $entry->volumeEntries->add(new VolumeEntry('ve-3', $entry, $v3));
        $entry->volumeEntries->add(new VolumeEntry('ve-1', $entry, $v1));
        $entry->volumeEntries->add(new VolumeEntry('ve-2', $entry, $v2));

        $arr = $entry->toDetailArray();

        $this->assertCount(3, $arr['volumes']);
        $this->assertSame(1, $arr['volumes'][0]['number']);
        $this->assertSame(2, $arr['volumes'][1]['number']);
        $this->assertSame(3, $arr['volumes'][2]['number']);
    }

    public function testWishedCountExcludesOwnedVolumes(): void
    {
        $entry = new CollectionEntry('ce-1', $this->manga);
        $v1 = new Volume('v-1', $this->manga, 1);

        // owned + wished => not counted as wished
        $ve = new VolumeEntry('ve-1', $entry, $v1, isOwned: true, isWished: true);
        $entry->volumeEntries->add($ve);

        $arr = $entry->toArray();
        $this->assertSame(0, $arr['wishedCount']);
    }
}
