<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\ClearWishlist;

use App\Collection\Application\ClearWishlist\ClearWishlistCommand;
use App\Collection\Application\ClearWishlist\ClearWishlistHandler;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Manga\Domain\Manga;
use App\Manga\Domain\Volume;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class ClearWishlistHandlerTest extends TestCase
{
    public function testSetsAllWishedToFalse(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $entry = new CollectionEntry('ce-1', $manga);
        $v1 = new Volume('v-1', $manga, 1);
        $v2 = new Volume('v-2', $manga, 2);
        $ve1 = new VolumeEntry('ve-1', $entry, $v1, isWished: true);
        $ve2 = new VolumeEntry('ve-2', $entry, $v2, isWished: true);
        $entry->volumeEntries->add($ve1);
        $entry->volumeEntries->add($ve2);

        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn($entry);
        $repository->expects($this->once())->method('save');

        $handler = new ClearWishlistHandler($repository);
        $handler(new ClearWishlistCommand('ce-1'));

        $this->assertFalse($ve1->isWished);
        $this->assertFalse($ve2->isWished);
    }

    public function testThrowsWhenEntryNotFound(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $handler = new ClearWishlistHandler($repository);

        $this->expectException(NotFoundException::class);
        $handler(new ClearWishlistCommand('missing'));
    }
}
