<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\Import;

use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Manga\Application\Import\ImportMangaCommand;
use App\Manga\Application\Import\ImportMangaHandler;
use App\Manga\Domain\Exception\TooManyVolumesException;
use App\Manga\Domain\GenreEnum;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Volume;
use App\Manga\Shared\Event\ImportMangaFailedEvent;
use App\Manga\Shared\Event\ImportMangaStartedEvent;
use App\Manga\Shared\Event\ImportMangaSucceededEvent;
use App\Shared\Domain\Security\CurrentUserProviderInterface;
use App\Tests\Doubles\Shared\RecordingEventBus;
use PHPUnit\Framework\TestCase;

final class ImportMangaHandlerTest extends TestCase
{
    private User $typist;
    private RecordingEventBus $eventBus;

    /** @var list<Manga> */
    private array $savedSeries = [];

    protected function setUp(): void
    {
        $this->typist   = new User(id: 'typist-1', email: 'typist@example.com', passwordHash: 'hash', displayName: 'Typist');
        $this->eventBus = new RecordingEventBus();
    }

    private function handler(?MangaRepositoryInterface $repository = null): ImportMangaHandler
    {
        if ($repository === null) {
            $repository = $this->createStub(MangaRepositoryInterface::class);
            $repository->method('save')->willReturnCallback(function (Manga $manga): void {
                $this->savedSeries[] = $manga;
            });
        }
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($this->typist);
        $currentUserProvider = $this->createStub(CurrentUserProviderInterface::class);
        $currentUserProvider->method('currentUserId')->willReturn('typist-1');

        return new ImportMangaHandler($repository, $userRepository, $currentUserProvider, $this->eventBus);
    }

    public function testASeriesTypedByHandBelongsToItsTypistWithEveryTome(): void
    {
        $mangaId = ($this->handler())(new ImportMangaCommand(
            title: 'Berserk',
            language: 'fr',
            edition: 'Glénat',
            specialEdition: 'Prestige',
            author: 'Kentaro Miura',
            genre: 'seinen',
            totalVolumes: 3,
        ));

        $this->assertCount(1, $this->savedSeries);
        $series = $this->savedSeries[0];
        $this->assertSame($mangaId, $series->id);
        $this->assertSame($this->typist, $series->owner);
        $this->assertSame('Prestige', $series->specialEdition);
        $this->assertSame(GenreEnum::Seinen, $series->genre);
        $this->assertSame([1, 2, 3], array_map(static fn (Volume $volume): int => $volume->number, $series->volumes->toArray()));
        $this->assertSame([ImportMangaStartedEvent::class, ImportMangaSucceededEvent::class], $this->eventBus->eventClasses());
        $this->assertSame($mangaId, $this->eventBus->first(ImportMangaSucceededEvent::class)->mangaId);
    }

    public function testWithoutAKnownTotalTheSeriesStartsWithoutTomes(): void
    {
        ($this->handler())(new ImportMangaCommand(title: 'One-shot', language: 'fr'));

        $this->assertCount(0, $this->savedSeries[0]->volumes);
        $this->assertNull($this->savedSeries[0]->genre);
    }

    public function testAnAbsurdTomeCountIsRefusedAndNothingSaved(): void
    {
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->expects($this->never())->method('save');

        try {
            ($this->handler($repository))(new ImportMangaCommand(title: 'X', language: 'fr', totalVolumes: 100_000));
            $this->fail('An absurd tome count must be refused.');
        } catch (TooManyVolumesException) {
        }

        $this->assertSame([ImportMangaStartedEvent::class, ImportMangaFailedEvent::class], $this->eventBus->eventClasses());
        $this->assertSame('X', $this->eventBus->first(ImportMangaFailedEvent::class)->title);
    }
}
