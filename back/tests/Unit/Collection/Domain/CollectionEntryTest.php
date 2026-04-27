<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Domain;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\ReadingStatusEnum;
use App\Collection\Domain\VolumeEntry;
use App\Manga\Domain\Manga;
use App\Manga\Domain\Volume;
use PHPUnit\Framework\TestCase;

final class CollectionEntryTest extends TestCase
{
    private function makeManga(string $id = 'm1'): Manga
    {
        return new Manga(id: $id, title: 'One Piece', edition: null, language: 'fr');
    }

    private function addVolume(CollectionEntry $entry, string $vid, int $number, bool $owned = false, bool $read = false, bool $wished = false, ?float $price = null): VolumeEntry
    {
        $volume = new Volume(id: $vid, manga: $entry->manga, number: $number, price: $price);
        $ve     = new VolumeEntry(
            id: 've-' . $vid,
            collectionEntry: $entry,
            volume: $volume,
            isOwned: $owned,
            isRead: $read,
            isWished: $wished,
        );
        $entry->volumeEntries->add($ve);

        return $ve;
    }

    public function testToArrayDefaults(): void
    {
        $manga = $this->makeManga();
        $entry = new CollectionEntry(id: 'ce1', manga: $manga);
        $arr   = $entry->toArray();

        $this->assertSame('ce1', $arr['id']);
        $this->assertSame('not_started', $arr['readingStatus']);
        $this->assertNull($arr['review']);
        $this->assertNull($arr['rating']);
        $this->assertSame(0, $arr['ownedCount']);
        $this->assertSame(0, $arr['readCount']);
        $this->assertSame(0, $arr['wishedCount']);
        $this->assertSame(0, $arr['totalVolumes']);
        $this->assertFalse($arr['notificationsEnabled']);
        $this->assertNull($arr['notificationsEnabledAt']);
        $this->assertSame(0.0, $arr['ownedValue']);
        $this->assertArrayHasKey('addedAt', $arr);
    }

    public function testOwnedCount(): void
    {
        $manga = $this->makeManga();
        $entry = new CollectionEntry(id: 'ce1', manga: $manga);
        $this->addVolume($entry, 'v1', 1, owned: true, price: 7.99);
        $this->addVolume($entry, 'v2', 2, owned: false, price: 7.99);

        $arr = $entry->toArray();

        $this->assertSame(1, $arr['ownedCount']);
        $this->assertSame(7.99, $arr['ownedValue']);
    }

    public function testReadCount(): void
    {
        $manga = $this->makeManga();
        $entry = new CollectionEntry(id: 'ce1', manga: $manga);
        $this->addVolume($entry, 'v1', 1, read: true);
        $this->addVolume($entry, 'v2', 2, read: true);
        $this->addVolume($entry, 'v3', 3, read: false);

        $this->assertSame(2, $entry->toArray()['readCount']);
    }

    public function testWishedCountExcludesOwned(): void
    {
        $manga = $this->makeManga();
        $entry = new CollectionEntry(id: 'ce1', manga: $manga);
        $this->addVolume($entry, 'v1', 1, owned: false, wished: true);
        $this->addVolume($entry, 'v2', 2, owned: true, wished: true);

        $this->assertSame(1, $entry->toArray()['wishedCount']);
    }

    public function testToDetailArrayIncludesVolumesSorted(): void
    {
        $manga = $this->makeManga();
        $entry = new CollectionEntry(id: 'ce1', manga: $manga);
        $this->addVolume($entry, 'v3', 3);
        $this->addVolume($entry, 'v1', 1);
        $this->addVolume($entry, 'v2', 2);

        $detail = $entry->toDetailArray();

        $this->assertArrayHasKey('volumes', $detail);
        $this->assertCount(3, $detail['volumes']);
        $this->assertSame(1, $detail['volumes'][0]['number']);
        $this->assertSame(2, $detail['volumes'][1]['number']);
        $this->assertSame(3, $detail['volumes'][2]['number']);
    }

    public function testReadingStatusInArray(): void
    {
        $manga = $this->makeManga();
        $entry = new CollectionEntry(id: 'ce1', manga: $manga, readingStatus: ReadingStatusEnum::InProgress);

        $this->assertSame('in_progress', $entry->toArray()['readingStatus']);
    }
}
