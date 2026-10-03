<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Domain\Service;

use App\Auth\Domain\User;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\ReadingStatusEnum;
use App\Collection\Domain\Service\CatalogueCollectionMatcher;
use App\Collection\Domain\Service\CatalogueEntryRegistrar;
use App\Manga\Domain\Catalogue\CatalogueEdition;
use App\Manga\Domain\Catalogue\CatalogueVolume;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Service\PublisherNormalizer;
use App\Manga\Domain\Volume;
use PHPUnit\Framework\TestCase;

final class CatalogueEntryRegistrarTest extends TestCase
{
    /** @var list<Manga> */
    private array $storedSeries = [];

    /** @var array<string, CollectionEntry> */
    private array $entriesByMangaId = [];

    private CatalogueEntryRegistrar $registrar;

    protected function setUp(): void
    {
        $mangaRepository = $this->createStub(MangaRepositoryInterface::class);
        $mangaRepository->method('findByTitles')->willReturnCallback(fn (): array => $this->storedSeries);
        $mangaRepository->method('save')->willReturnCallback(function (Manga $manga): void {
            if (!in_array($manga, $this->storedSeries, true)) {
                $this->storedSeries[] = $manga;
            }
        });

        $collectionRepository = $this->createStub(CollectionRepositoryInterface::class);
        $collectionRepository->method('findByMangaId')
            ->willReturnCallback(fn (string $mangaId): ?CollectionEntry => $this->entriesByMangaId[$mangaId] ?? null);
        $collectionRepository->method('findByMangaIds')->willReturnCallback(fn (): array => array_values($this->entriesByMangaId));
        $collectionRepository->method('save')->willReturnCallback(function (CollectionEntry $entry): void {
            $this->entriesByMangaId[$entry->manga->id] = $entry;
        });

        $this->registrar = new CatalogueEntryRegistrar(
            new CatalogueCollectionMatcher($mangaRepository, $collectionRepository, new PublisherNormalizer()),
            $mangaRepository,
            $collectionRepository,
        );
    }

    private function prestige(int $volumeCount = 3): CatalogueEdition
    {
        return new CatalogueEdition(
            workTitle: 'Berserk',
            publisher: 'Glénat',
            specialEdition: 'Prestige',
            author: 'Kentaro Miura',
            coverUrl: 'https://covers.example/1.jpg',
            volumeCount: $volumeCount,
            volumes: [
                new CatalogueVolume(1, Isbn::fromString('9782344036075'), 'https://covers.example/1.jpg'),
                new CatalogueVolume(2, Isbn::fromString('9782344036082'), null),
            ],
        );
    }

    public function testANewSeriesIsCreatedWithAllItsTomesAndThePickedOnesOwned(): void
    {
        $registration = $this->registrar->register($this->prestige(), [2], null);

        $this->assertTrue($registration->seriesCreated);
        $this->assertTrue($registration->entryCreated);
        $this->assertSame([2], $registration->addedNumbers);
        $this->assertSame([], $registration->alreadyOwnedNumbers);
        $this->assertSame(3, $registration->totalVolumes);
        $this->assertFalse($registration->addedNothing());

        $manga = $this->storedSeries[0];
        $this->assertSame('Berserk', $manga->title);
        $this->assertSame('Glénat', $manga->edition);
        $this->assertSame('Prestige', $manga->specialEdition);
        $this->assertSame('fr', $manga->language);
        $this->assertSame('9782344036082', $manga->volumeByNumber(2)?->isbn?->value);
        $this->assertSame('https://covers.example/1.jpg', $manga->volumeByNumber(1)?->coverUrl);

        $entry = $this->entriesByMangaId[$manga->id];
        $this->assertCount(3, $entry->volumeEntries);
        $this->assertTrue($entry->volumeEntryForNumber(2)?->isOwned);
        $this->assertFalse($entry->volumeEntryForNumber(1)?->isOwned);
        $this->assertSame(ReadingStatusEnum::InProgress, $entry->readingStatus);
        $this->assertSame($entry->volumeEntryForNumber(2)?->id, $registration->volumeEntryIds[2]);
    }

    public function testAnExistingSeriesIsReusedAndOnlyNewTomesAreAdded(): void
    {
        $first  = $this->registrar->register($this->prestige(), [1], null);
        $second = $this->registrar->register($this->prestige(), [1, 2], null);

        $this->assertCount(1, $this->storedSeries);
        $this->assertFalse($second->seriesCreated);
        $this->assertFalse($second->entryCreated);
        $this->assertSame($first->collectionEntryId, $second->collectionEntryId);
        $this->assertSame([2], $second->addedNumbers);
        $this->assertSame([1], $second->alreadyOwnedNumbers);
    }

    public function testAskingOnlyOwnedTomesAddsNothing(): void
    {
        $this->registrar->register($this->prestige(), [1], null);

        $registration = $this->registrar->register($this->prestige(), [1], null);

        $this->assertTrue($registration->addedNothing());
    }

    public function testATomeBeyondTheKnownLengthExtendsTheSeries(): void
    {
        $registration = $this->registrar->register($this->prestige(), [5], null);

        $this->assertSame(5, $registration->totalVolumes);
        $this->assertSame([5], $registration->addedNumbers);
    }

    public function testCatalogueDataNeverOverwritesWhatTheUserSet(): void
    {
        $manga = new Manga(id: 'm-existing', title: 'Berserk', edition: 'Glénat', language: 'fr', coverUrl: 'https://mine.example/cover.jpg', specialEdition: 'Prestige');
        $manga->addVolume(new Volume(id: 'v1', manga: $manga, number: 1, coverUrl: 'https://mine.example/1.jpg', isbn: Isbn::fromString('9782723425483')));
        $this->storedSeries[] = $manga;

        $registration = $this->registrar->register($this->prestige(), [], null);

        $this->assertFalse($registration->seriesCreated);
        $this->assertTrue($registration->entryCreated);
        $this->assertSame('https://mine.example/cover.jpg', $manga->coverUrl);
        $this->assertSame('https://mine.example/1.jpg', $manga->volumeByNumber(1)?->coverUrl);
        $this->assertSame('9782723425483', $manga->volumeByNumber(1)?->isbn?->value);
        $this->assertSame('Kentaro Miura', $manga->author);
        $this->assertSame([], $registration->addedNumbers);
    }

    public function testANewSeriesBelongsToWhoeverAddsIt(): void
    {
        $owner = $this->account('reader-1');

        $registration = $this->registrar->register($this->prestige(), [1], $owner);

        $this->assertTrue($registration->seriesCreated);
        $this->assertSame($owner, $this->storedSeries[0]->owner);
        $this->assertSame($owner, $this->entriesByMangaId[$registration->mangaId]->owner);
    }

    public function testOnesOwnCopyIsReused(): void
    {
        $owner = $this->account('reader-1');
        $first = $this->registrar->register($this->prestige(), [1], $owner);

        $second = $this->registrar->register($this->prestige(), [2], $owner);

        $this->assertFalse($second->seriesCreated);
        $this->assertSame($first->mangaId, $second->mangaId);
    }

    /** Even where no owner filter runs, another account's copy is never written into. */
    public function testAnotherAccountsCopyIsNeverReused(): void
    {
        $someoneElse = new Manga(
            id: 'm-someone-else',
            title: 'Berserk',
            edition: 'Glénat',
            language: 'fr',
            specialEdition: 'Prestige',
            owner: $this->account('reader-2'),
        );
        $this->storedSeries[] = $someoneElse;

        $registration = $this->registrar->register($this->prestige(), [1], $this->account('reader-1'));

        $this->assertTrue($registration->seriesCreated);
        $this->assertNotSame('m-someone-else', $registration->mangaId);
        $this->assertCount(0, $someoneElse->volumes);
    }

    private function account(string $id): User
    {
        return new User(id: $id, email: $id . '@example.com', passwordHash: 'hash', displayName: $id);
    }
}
