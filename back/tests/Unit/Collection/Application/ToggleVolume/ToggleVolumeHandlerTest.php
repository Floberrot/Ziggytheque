<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\ToggleVolume;

use App\Collection\Application\ToggleVolume\ToggleVolumeCommand;
use App\Collection\Application\ToggleVolume\ToggleVolumeHandler;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Manga\Domain\Manga;
use App\Manga\Domain\Volume;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class ToggleVolumeHandlerTest extends TestCase
{
    private Manga $manga;
    private Volume $volume;
    private CollectionEntry $entry;
    private VolumeEntry $volumeEntry;

    protected function setUp(): void
    {
        $this->manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $this->volume = new Volume('v-1', $this->manga, 1);
        $this->entry = new CollectionEntry('ce-1', $this->manga);
        $this->volumeEntry = new VolumeEntry('ve-1', $this->entry, $this->volume);
        $this->entry->volumeEntries->add($this->volumeEntry);
    }

    public function testTogglesIsOwned(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn($this->entry);

        $handler = new ToggleVolumeHandler($repository);
        $handler(new ToggleVolumeCommand('ce-1', 've-1', 'isOwned'));

        $this->assertTrue($this->volumeEntry->isOwned);
    }

    public function testSettingIsOwnedClearsIsWished(): void
    {
        $this->volumeEntry->isWished = true;

        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn($this->entry);

        $handler = new ToggleVolumeHandler($repository);
        $handler(new ToggleVolumeCommand('ce-1', 've-1', 'isOwned'));

        $this->assertTrue($this->volumeEntry->isOwned);
        $this->assertFalse($this->volumeEntry->isWished);
    }

    public function testTogglesIsRead(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn($this->entry);

        $handler = new ToggleVolumeHandler($repository);
        $handler(new ToggleVolumeCommand('ce-1', 've-1', 'isRead'));

        $this->assertTrue($this->volumeEntry->isRead);
    }

    public function testTogglesIsWished(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn($this->entry);

        $handler = new ToggleVolumeHandler($repository);
        $handler(new ToggleVolumeCommand('ce-1', 've-1', 'isWished'));

        $this->assertTrue($this->volumeEntry->isWished);
    }

    public function testThrowsWhenEntryNotFound(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $handler = new ToggleVolumeHandler($repository);

        $this->expectException(NotFoundException::class);
        $handler(new ToggleVolumeCommand('missing', 've-1', 'isOwned'));
    }

    public function testThrowsWhenVolumeEntryNotFound(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn($this->entry);

        $handler = new ToggleVolumeHandler($repository);

        $this->expectException(NotFoundException::class);
        $handler(new ToggleVolumeCommand('ce-1', 'missing-ve', 'isOwned'));
    }
}
