<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\UpdateVolume;

use App\Manga\Application\UpdateVolume\UpdateVolumeCommand;
use App\Manga\Application\UpdateVolume\UpdateVolumeHandler;
use App\Manga\Domain\Exception\InvalidIsbnException;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Volume;
use App\Manga\Shared\Event\UpdateVolumeSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateVolumeHandlerTest extends TestCase
{
    private const string MANGA_ID  = 'manga-1';
    private const string VOLUME_ID = 'volume-1';

    private MangaRepositoryInterface&MockObject $repository;
    private EventBusInterface&MockObject $eventBus;
    private UpdateVolumeHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(MangaRepositoryInterface::class);
        $this->eventBus   = $this->createMock(EventBusInterface::class);
        $this->handler    = new UpdateVolumeHandler($this->repository, $this->eventBus);
    }

    private function makeMangaWithVolume(): Manga
    {
        $manga  = new Manga(id: self::MANGA_ID, title: 'Berserk', edition: null, language: 'fr');
        $volume = new Volume(id: self::VOLUME_ID, manga: $manga, number: 1);
        $manga->addVolume($volume);

        return $manga;
    }

    private function firstVolume(Manga $manga): Volume
    {
        $volume = $manga->volumes->first();
        $this->assertInstanceOf(Volume::class, $volume);

        return $volume;
    }

    public function testThrowsNotFoundForUnknownManga(): void
    {
        $this->repository->method('findById')->willReturn(null);
        $this->repository->expects($this->never())->method('save');

        $this->expectException(NotFoundException::class);

        ($this->handler)(new UpdateVolumeCommand(mangaId: self::MANGA_ID, volumeId: self::VOLUME_ID));
    }

    public function testThrowsNotFoundForUnknownVolume(): void
    {
        $this->repository->method('findById')->willReturn($this->makeMangaWithVolume());
        $this->repository->expects($this->never())->method('save');

        $this->expectException(NotFoundException::class);

        ($this->handler)(new UpdateVolumeCommand(mangaId: self::MANGA_ID, volumeId: 'other-volume'));
    }

    public function testPersistsIsbnAloneNormalizedToIsbn13(): void
    {
        $manga = $this->makeMangaWithVolume();
        $this->repository->method('findById')->willReturn($manga);
        $this->repository->expects($this->once())->method('save')->with($manga);

        ($this->handler)(new UpdateVolumeCommand(
            mangaId:  self::MANGA_ID,
            volumeId: self::VOLUME_ID,
            isbn:     '978-2-7234-2548-3',
        ));

        $volume = $this->firstVolume($manga);
        $this->assertInstanceOf(Isbn::class, $volume->isbn);
        $this->assertSame('9782723425483', $volume->isbn->value);
    }

    public function testConvertsScannedIsbn10ToIsbn13(): void
    {
        $manga = $this->makeMangaWithVolume();
        $this->repository->method('findById')->willReturn($manga);

        // 2723425487 is a checksum-valid ISBN-10 (converts to 9782723425483)
        ($this->handler)(new UpdateVolumeCommand(
            mangaId:  self::MANGA_ID,
            volumeId: self::VOLUME_ID,
            isbn:     '2723425487',
        ));

        $volume = $this->firstVolume($manga);
        $this->assertSame('9782723425483', $volume->isbn?->value);
    }

    public function testInvalidIsbnThrowsAndSavesNothing(): void
    {
        $manga = $this->makeMangaWithVolume();
        $this->repository->method('findById')->willReturn($manga);
        $this->repository->expects($this->never())->method('save');
        $this->eventBus->expects($this->never())->method('publish');

        $this->expectException(InvalidIsbnException::class);

        ($this->handler)(new UpdateVolumeCommand(
            mangaId:  self::MANGA_ID,
            volumeId: self::VOLUME_ID,
            isbn:     'not-an-isbn',
        ));
    }

    public function testUpdatesOnlyTheProvidedFields(): void
    {
        $manga = $this->makeMangaWithVolume();
        $this->repository->method('findById')->willReturn($manga);

        ($this->handler)(new UpdateVolumeCommand(
            mangaId:     self::MANGA_ID,
            volumeId:    self::VOLUME_ID,
            coverUrl:    'https://covers.example/berserk-1.jpg',
            releaseDate: '2026-04-01',
            price:       7.99,
            spineUrl:    'https://covers.example/berserk-1-spine.jpg',
        ));

        $volume = $this->firstVolume($manga);
        $this->assertSame('https://covers.example/berserk-1.jpg', $volume->coverUrl);
        $this->assertSame('2026-04-01', $volume->releaseDate?->format('Y-m-d'));
        $this->assertSame(7.99, $volume->price);
        $this->assertSame('https://covers.example/berserk-1-spine.jpg', $volume->spineUrl);
        $this->assertNull($volume->isbn);
    }

    public function testPublishesSucceededEventAfterSave(): void
    {
        $manga = $this->makeMangaWithVolume();
        $this->repository->method('findById')->willReturn($manga);

        $this->eventBus->expects($this->once())
            ->method('publish')
            ->with($this->callback(
                static fn (UpdateVolumeSucceededEvent $event) => $event->mangaId === self::MANGA_ID
                    && $event->volumeId === self::VOLUME_ID,
            ));

        ($this->handler)(new UpdateVolumeCommand(
            mangaId:  self::MANGA_ID,
            volumeId: self::VOLUME_ID,
            price:    5.0,
        ));
    }
}
