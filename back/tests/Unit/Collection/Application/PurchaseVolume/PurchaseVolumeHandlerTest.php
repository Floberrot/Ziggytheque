<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\PurchaseVolume;

use App\Collection\Application\PurchaseVolume\PurchaseVolumeCommand;
use App\Collection\Application\PurchaseVolume\PurchaseVolumeHandler;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Manga\Domain\Manga;
use App\Manga\Domain\Volume;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class PurchaseVolumeHandlerTest extends TestCase
{
    public function testSetsOwnedTrueAndWishedFalse(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $volume = new Volume('v-1', $manga, 1);
        $entry = new CollectionEntry('ce-1', $manga);
        $ve = new VolumeEntry('ve-1', $entry, $volume, isOwned: false, isWished: true);
        $entry->volumeEntries->add($ve);

        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn($entry);
        $repository->expects($this->once())->method('save');

        $handler = new PurchaseVolumeHandler($repository);
        $handler(new PurchaseVolumeCommand('ce-1', 've-1'));

        $this->assertTrue($ve->isOwned);
        $this->assertFalse($ve->isWished);
    }

    public function testThrowsWhenEntryNotFound(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $handler = new PurchaseVolumeHandler($repository);

        $this->expectException(NotFoundException::class);
        $handler(new PurchaseVolumeCommand('missing', 've-1'));
    }

    public function testThrowsWhenVolumeEntryNotFound(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $entry = new CollectionEntry('ce-1', $manga);

        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn($entry);

        $handler = new PurchaseVolumeHandler($repository);

        $this->expectException(NotFoundException::class);
        $handler(new PurchaseVolumeCommand('ce-1', 'missing-ve'));
    }
}
