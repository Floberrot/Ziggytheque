<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\Update;

use App\Manga\Application\Update\UpdateMangaCommand;
use App\Manga\Application\Update\UpdateMangaHandler;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Shared\Event\UpdateMangaFailedEvent;
use App\Manga\Shared\Event\UpdateMangaStartedEvent;
use App\Manga\Shared\Event\UpdateMangaSucceededEvent;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Doubles\Shared\RecordingEventBus;
use PHPUnit\Framework\TestCase;

final class UpdateMangaHandlerTest extends TestCase
{
    private RecordingEventBus $eventBus;

    protected function setUp(): void
    {
        $this->eventBus = new RecordingEventBus();
    }

    private function series(): Manga
    {
        return new Manga(
            id: 'manga-1',
            title: 'Berserk',
            edition: 'Glénat',
            language: 'fr',
            coverUrl: 'https://covers.example/old.jpg',
            specialEdition: 'Prestige',
        );
    }

    private function handlerFor(?Manga $storedManga): UpdateMangaHandler
    {
        $repository = $this->createStub(MangaRepositoryInterface::class);
        $repository->method('findById')->willReturn($storedManga);

        return new UpdateMangaHandler($repository, $this->eventBus);
    }

    public function testChangesTheGivenFieldsSavesAndJournals(): void
    {
        $manga      = $this->series();
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->with('manga-1')->willReturn($manga);
        $repository->expects($this->once())->method('save')->with($manga);

        (new UpdateMangaHandler($repository, $this->eventBus))(new UpdateMangaCommand(
            mangaId: 'manga-1',
            title: 'Berserk (my title)',
            edition: 'Glénat Manga',
            specialEdition: 'Perfect edition',
            coverUrl: 'https://covers.example/new.jpg',
        ));

        $this->assertSame('Berserk (my title)', $manga->title);
        $this->assertSame('Glénat Manga', $manga->edition);
        $this->assertSame('Perfect edition', $manga->specialEdition);
        $this->assertSame('https://covers.example/new.jpg', $manga->coverUrl);
        $this->assertSame([UpdateMangaStartedEvent::class, UpdateMangaSucceededEvent::class], $this->eventBus->eventClasses());
        $started   = $this->eventBus->first(UpdateMangaStartedEvent::class);
        $succeeded = $this->eventBus->first(UpdateMangaSucceededEvent::class);
        $this->assertSame('manga-1', $started->mangaId);
        $this->assertSame($started->correlationId, $succeeded->correlationId);
        $this->assertSame('Berserk (my title)', $succeeded->mangaTitle);
    }

    public function testFieldsLeftOutStayAsTheyAre(): void
    {
        $manga = $this->series();

        ($this->handlerFor($manga))(new UpdateMangaCommand(mangaId: 'manga-1', title: 'Berserk Deluxe'));

        $this->assertSame('Berserk Deluxe', $manga->title);
        $this->assertSame('Glénat', $manga->edition);
        $this->assertSame('Prestige', $manga->specialEdition);
        $this->assertSame('https://covers.example/old.jpg', $manga->coverUrl);
    }

    public function testAnEmptyStringClearsTheOptionalFields(): void
    {
        $manga = $this->series();

        ($this->handlerFor($manga))(new UpdateMangaCommand(
            mangaId: 'manga-1',
            edition: '',
            specialEdition: '',
            coverUrl: '',
        ));

        $this->assertSame('Berserk', $manga->title);
        $this->assertNull($manga->edition);
        $this->assertNull($manga->specialEdition);
        $this->assertNull($manga->coverUrl);
    }

    public function testAnUnknownSeriesIsNotFoundAndJournaledAsFailed(): void
    {
        try {
            ($this->handlerFor(null))(new UpdateMangaCommand(mangaId: 'ghost', title: 'Anything'));
            $this->fail('An unknown series must be refused.');
        } catch (NotFoundException) {
        }

        $this->assertSame([UpdateMangaStartedEvent::class, UpdateMangaFailedEvent::class], $this->eventBus->eventClasses());
        $failed = $this->eventBus->first(UpdateMangaFailedEvent::class);
        $this->assertSame('ghost', $failed->mangaId);
        $this->assertSame(NotFoundException::class, $failed->exceptionClass);
    }
}
