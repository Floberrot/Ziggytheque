<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\Add;

use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Collection\Application\Add\AddToCollectionCommand;
use App\Collection\Application\Add\AddToCollectionHandler;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Shared\Event\AddToCollectionFailedEvent;
use App\Collection\Shared\Event\AddToCollectionStartedEvent;
use App\Collection\Shared\Event\AddToCollectionSucceededEvent;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Security\CurrentUserProviderInterface;
use App\Tests\Doubles\Shared\RecordingEventBus;
use PHPUnit\Framework\TestCase;

final class AddToCollectionHandlerTest extends TestCase
{
    private User $reader;
    private RecordingEventBus $eventBus;

    protected function setUp(): void
    {
        $this->reader   = new User(id: 'reader-1', email: 'reader@example.com', passwordHash: 'hash', displayName: 'Reader');
        $this->eventBus = new RecordingEventBus();
    }

    private function handler(?Manga $storedManga, CollectionRepositoryInterface $collectionRepository): AddToCollectionHandler
    {
        $mangaRepository = $this->createStub(MangaRepositoryInterface::class);
        $mangaRepository->method('findById')->willReturn($storedManga);
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($this->reader);
        $currentUserProvider = $this->createStub(CurrentUserProviderInterface::class);
        $currentUserProvider->method('currentUserId')->willReturn('reader-1');

        return new AddToCollectionHandler(
            $collectionRepository,
            $mangaRepository,
            $userRepository,
            $currentUserProvider,
            $this->eventBus,
        );
    }

    private function ownSeriesWithTomes(int $tomes, ?User $owner): Manga
    {
        $manga = new Manga(id: 'manga-1', title: 'Berserk', edition: null, language: 'fr', owner: $owner);
        $manga->ensureVolumesUpTo($tomes);

        return $manga;
    }

    public function testCollectsOnesOwnSeriesWithEveryTomeTracked(): void
    {
        $manga = $this->ownSeriesWithTomes(3, $this->reader);

        $savedEntries         = [];
        $collectionRepository = $this->createMock(CollectionRepositoryInterface::class);
        $collectionRepository->expects($this->once())->method('save')->willReturnCallback(
            static function (CollectionEntry $entry) use (&$savedEntries): void {
                $savedEntries[] = $entry;
            },
        );

        $entryId = ($this->handler($manga, $collectionRepository))(new AddToCollectionCommand('manga-1'));

        $this->assertCount(1, $savedEntries);
        $this->assertSame($entryId, $savedEntries[0]->id);
        $this->assertSame($manga, $savedEntries[0]->manga);
        $this->assertSame($this->reader, $savedEntries[0]->owner);
        $this->assertCount(3, $savedEntries[0]->volumeEntries);
        $this->assertSame([AddToCollectionStartedEvent::class, AddToCollectionSucceededEvent::class], $this->eventBus->eventClasses());
        $succeeded = $this->eventBus->first(AddToCollectionSucceededEvent::class);
        $this->assertSame($entryId, $succeeded->collectionEntryId);
        $this->assertSame('Berserk', $succeeded->mangaTitle);
    }

    /** Another account's copy does not exist for this user. */
    public function testAnotherAccountsSeriesIsNotFound(): void
    {
        $someoneElse = new User(id: 'other-1', email: 'other@example.com', passwordHash: 'hash', displayName: 'Other');
        $collectionRepository = $this->createMock(CollectionRepositoryInterface::class);
        $collectionRepository->expects($this->never())->method('save');

        try {
            ($this->handler($this->ownSeriesWithTomes(1, $someoneElse), $collectionRepository))(new AddToCollectionCommand('manga-1'));
            $this->fail('Another account\'s series must stay out of reach.');
        } catch (NotFoundException) {
        }

        $this->assertSame([AddToCollectionStartedEvent::class, AddToCollectionFailedEvent::class], $this->eventBus->eventClasses());
    }

    public function testAnUnknownSeriesIsNotFound(): void
    {
        $collectionRepository = $this->createMock(CollectionRepositoryInterface::class);
        $collectionRepository->expects($this->never())->method('save');

        try {
            ($this->handler(null, $collectionRepository))(new AddToCollectionCommand('ghost'));
            $this->fail('An unknown series must be refused.');
        } catch (NotFoundException) {
        }

        $this->assertSame('ghost', $this->eventBus->first(AddToCollectionFailedEvent::class)->mangaId);
    }
}
