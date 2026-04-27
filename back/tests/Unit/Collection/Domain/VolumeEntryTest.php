<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Domain;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\VolumeEntry;
use App\Manga\Domain\Manga;
use App\Manga\Domain\Volume;
use PHPUnit\Framework\TestCase;

final class VolumeEntryTest extends TestCase
{
    private function makeVolumeEntry(bool $owned = false, bool $read = false, bool $wished = false): VolumeEntry
    {
        $manga  = new Manga(id: 'm1', title: 'Test', edition: null, language: 'fr');
        $volume = new Volume(id: 'v1', manga: $manga, number: 1, price: 7.99);
        $entry  = new CollectionEntry(id: 'ce1', manga: $manga);

        return new VolumeEntry(
            id: 've1',
            collectionEntry: $entry,
            volume: $volume,
            isOwned: $owned,
            isRead: $read,
            isWished: $wished,
        );
    }

    public function testToArray(): void
    {
        $ve  = $this->makeVolumeEntry(owned: true, read: false, wished: false);
        $arr = $ve->toArray();

        $this->assertSame('ve1', $arr['id']);
        $this->assertSame('v1', $arr['volumeId']);
        $this->assertSame(1, $arr['number']);
        $this->assertSame(7.99, $arr['price']);
        $this->assertTrue($arr['isOwned']);
        $this->assertFalse($arr['isRead']);
        $this->assertFalse($arr['isWished']);
        $this->assertFalse($arr['isAnnounced']);
        $this->assertNull($arr['review']);
        $this->assertNull($arr['rating']);
    }

    public function testToArrayWithReviewAndRating(): void
    {
        $ve         = $this->makeVolumeEntry();
        $ve->review = 'Great volume!';
        $ve->rating = 8;
        $arr        = $ve->toArray();

        $this->assertSame('Great volume!', $arr['review']);
        $this->assertSame(8, $arr['rating']);
    }
}
