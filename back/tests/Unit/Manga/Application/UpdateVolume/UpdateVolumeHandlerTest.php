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
use App\Manga\Shared\Event\UpdateVolumeFailedEvent;
use App\Manga\Shared\Event\UpdateVolumeStartedEvent;
use App\Manga\Shared\Event\UpdateVolumeSucceededEvent;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Doubles\Shared\RecordingEventBus;
use PHPUnit\Framework\TestCase;

final class UpdateVolumeHandlerTest extends TestCase
{
    private const string MANGA_ID  = 'manga-1';
    private const string VOLUME_ID = 'volume-1';

    private RecordingEventBus $eventBus;

    protected function setUp(): void
    {
        $this->eventBus = new RecordingEventBus();
    }

    private function handler(?Manga $storedManga, ?MangaRepositoryInterface $repository = null): UpdateVolumeHandler
    {
        if ($repository === null) {
            $repository = $this->createStub(MangaRepositoryInterface::class);
            $repository->method('findById')->willReturn($storedManga);
        }

        return new UpdateVolumeHandler($repository, $this->eventBus);
    }

    /** A repository that must save exactly this series, or nothing at all. */
    private function repositoryExpectingSaves(?Manga $storedManga, int $saves): MangaRepositoryInterface
    {
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->with(self::MANGA_ID)->willReturn($storedManga);
        $repository->expects($this->exactly($saves))->method('save');

        return $repository;
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
        $handler = $this->handler(null, $this->repositoryExpectingSaves(null, 0));

        try {
            $handler(new UpdateVolumeCommand(mangaId: self::MANGA_ID, volumeId: self::VOLUME_ID));
            $this->fail('An unknown series must be refused.');
        } catch (NotFoundException) {
        }

        $this->assertSame([UpdateVolumeStartedEvent::class, UpdateVolumeFailedEvent::class], $this->eventBus->eventClasses());
        $failed = $this->eventBus->first(UpdateVolumeFailedEvent::class);
        $this->assertSame(NotFoundException::class, $failed->exceptionClass);
        $this->assertSame(self::VOLUME_ID, $failed->volumeId);
    }

    public function testThrowsNotFoundForUnknownVolume(): void
    {
        $handler = $this->handler(null, $this->repositoryExpectingSaves($this->makeMangaWithVolume(), 0));

        $this->expectException(NotFoundException::class);

        $handler(new UpdateVolumeCommand(mangaId: self::MANGA_ID, volumeId: 'other-volume'));
    }

    public function testPersistsIsbnAloneNormalizedToIsbn13(): void
    {
        $manga = $this->makeMangaWithVolume();

        ($this->handler(null, $this->repositoryExpectingSaves($manga, 1)))(new UpdateVolumeCommand(
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

        // 2723425487 is a checksum-valid ISBN-10 (converts to 9782723425483)
        ($this->handler($manga))(new UpdateVolumeCommand(
            mangaId:  self::MANGA_ID,
            volumeId: self::VOLUME_ID,
            isbn:     '2723425487',
        ));

        $volume = $this->firstVolume($manga);
        $this->assertSame('9782723425483', $volume->isbn?->value);
    }

    public function testInvalidIsbnThrowsAndSavesNothing(): void
    {
        $handler = $this->handler(null, $this->repositoryExpectingSaves($this->makeMangaWithVolume(), 0));

        try {
            $handler(new UpdateVolumeCommand(
                mangaId:  self::MANGA_ID,
                volumeId: self::VOLUME_ID,
                isbn:     'not-an-isbn',
            ));
            $this->fail('An invalid ISBN must be refused.');
        } catch (InvalidIsbnException) {
        }

        $this->assertSame([UpdateVolumeStartedEvent::class, UpdateVolumeFailedEvent::class], $this->eventBus->eventClasses());
    }

    public function testUpdatesOnlyTheProvidedFields(): void
    {
        $manga = $this->makeMangaWithVolume();

        ($this->handler($manga))(new UpdateVolumeCommand(
            mangaId:     self::MANGA_ID,
            volumeId:    self::VOLUME_ID,
            coverUrl:    'https://covers.example/berserk-1.jpg',
            releaseDate: '2026-04-01',
            price:       7.99,
        ));

        $volume = $this->firstVolume($manga);
        $this->assertSame('https://covers.example/berserk-1.jpg', $volume->coverUrl);
        $this->assertSame('2026-04-01', $volume->releaseDate?->format('Y-m-d'));
        $this->assertSame(7.99, $volume->price);
        $this->assertNull($volume->isbn);
    }

    public function testJournalsTheChangeFromStartToSuccess(): void
    {
        ($this->handler($this->makeMangaWithVolume()))(new UpdateVolumeCommand(
            mangaId:  self::MANGA_ID,
            volumeId: self::VOLUME_ID,
            price:    5.0,
        ));

        $this->assertSame([UpdateVolumeStartedEvent::class, UpdateVolumeSucceededEvent::class], $this->eventBus->eventClasses());
        $started   = $this->eventBus->first(UpdateVolumeStartedEvent::class);
        $succeeded = $this->eventBus->first(UpdateVolumeSucceededEvent::class);
        $this->assertSame($started->correlationId, $succeeded->correlationId);
        $this->assertSame(self::MANGA_ID, $succeeded->mangaId);
        $this->assertSame(self::VOLUME_ID, $succeeded->volumeId);
        $this->assertSame('Berserk', $succeeded->mangaTitle);
        $this->assertSame(1, $succeeded->number);
    }
}
