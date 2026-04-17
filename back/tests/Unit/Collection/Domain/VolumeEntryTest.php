<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Domain;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\ReadingStatusEnum;
use App\Collection\Domain\VolumeEntry;
use App\Manga\Domain\Manga;
use App\Manga\Domain\Volume;
use PHPUnit\Framework\TestCase;

class VolumeEntryTest extends TestCase
{
    private Manga $manga;
    private Volume $volume;
    private CollectionEntry $entry;

    protected function setUp(): void
    {
        $this->manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $this->volume = new Volume('v-1', $this->manga, 1);
        $this->entry = new CollectionEntry('ce-1', $this->manga);
    }

    public function testDefaultsAreFalse(): void
    {
        $ve = new VolumeEntry('ve-1', $this->entry, $this->volume);

        $this->assertFalse($ve->isOwned);
        $this->assertFalse($ve->isRead);
        $this->assertFalse($ve->isWished);
        $this->assertNull($ve->review);
        $this->assertNull($ve->rating);
    }

    public function testToArrayContainsExpectedKeys(): void
    {
        $ve = new VolumeEntry('ve-1', $this->entry, $this->volume, true, false, true);
        $arr = $ve->toArray();

        $this->assertSame('ve-1', $arr['id']);
        $this->assertSame('v-1', $arr['volumeId']);
        $this->assertSame(1, $arr['number']);
        $this->assertTrue($arr['isOwned']);
        $this->assertFalse($arr['isRead']);
        $this->assertTrue($arr['isWished']);
    }
}
