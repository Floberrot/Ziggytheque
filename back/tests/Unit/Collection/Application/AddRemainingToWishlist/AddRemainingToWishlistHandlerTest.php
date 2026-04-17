<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\AddRemainingToWishlist;

use App\Collection\Application\AddRemainingToWishlist\AddRemainingToWishlistCommand;
use App\Collection\Application\AddRemainingToWishlist\AddRemainingToWishlistHandler;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Manga\Domain\Manga;
use App\Manga\Domain\Volume;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class AddRemainingToWishlistHandlerTest extends TestCase
{
    public function testSetsWishedTrueForNonOwnedVolumes(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $entry = new CollectionEntry('ce-1', $manga);
        $v1 = new Volume('v-1', $manga, 1);
        $v2 = new Volume('v-2', $manga, 2);
        $ve1 = new VolumeEntry('ve-1', $entry, $v1, isOwned: true);
        $ve2 = new VolumeEntry('ve-2', $entry, $v2, isOwned: false);
        $entry->volumeEntries->add($ve1);
        $entry->volumeEntries->add($ve2);

        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn($entry);
        $repository->expects($this->once())->method('save');

        $handler = new AddRemainingToWishlistHandler($repository);
        $handler(new AddRemainingToWishlistCommand('ce-1'));

        $this->assertFalse($ve1->isWished);
        $this->assertTrue($ve2->isWished);
    }

    public function testThrowsWhenEntryNotFound(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $handler = new AddRemainingToWishlistHandler($repository);

        $this->expectException(NotFoundException::class);
        $handler(new AddRemainingToWishlistCommand('missing'));
    }
}
