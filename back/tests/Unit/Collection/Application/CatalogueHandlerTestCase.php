<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application;

use App\Auth\Domain\UserRepositoryInterface;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\Service\CatalogueCollectionMatcher;
use App\Collection\Domain\Service\CatalogueEntryRegistrar;
use App\Manga\Domain\Catalogue\CatalogueRecord;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Service\CatalogueEditionAssembler;
use App\Manga\Domain\Service\CatalogueSearch;
use App\Manga\Domain\Service\CatalogueTitleParser;
use App\Manga\Domain\Service\PublisherNormalizer;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Security\CurrentUserProviderInterface;
use App\Tests\Doubles\Manga\InMemoryCatalogue;
use PHPUnit\Framework\TestCase;

/**
 * Real catalogue services wired on in-memory stores, so handler tests exercise the
 * orchestration (events, return shape) without Doctrine nor the network.
 */
abstract class CatalogueHandlerTestCase extends TestCase
{
    protected InMemoryCatalogue $catalogue;
    protected CatalogueSearch $catalogueSearch;
    protected CatalogueCollectionMatcher $matcher;
    protected CatalogueEntryRegistrar $registrar;
    protected UserRepositoryInterface $userRepository;
    protected CurrentUserProviderInterface $currentUserProvider;
    protected EventBusInterface $eventBus;

    /** @var list<object> */
    protected array $publishedEvents = [];

    /** @var list<Manga> */
    private array $storedSeries = [];

    /** @var array<string, CollectionEntry> */
    private array $entriesByMangaId = [];

    protected function setUp(): void
    {
        $publisherNormalizer = new PublisherNormalizer();
        $this->catalogue = new InMemoryCatalogue();
        $this->catalogueSearch = new CatalogueSearch(
            $this->catalogue,
            new CatalogueTitleParser(),
            new CatalogueEditionAssembler($publisherNormalizer),
            $publisherNormalizer,
        );

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

        $this->matcher   = new CatalogueCollectionMatcher($mangaRepository, $collectionRepository, $publisherNormalizer);
        $this->registrar = new CatalogueEntryRegistrar($this->matcher, $mangaRepository, $collectionRepository);

        $this->userRepository = $this->createStub(UserRepositoryInterface::class);
        $this->currentUserProvider = $this->createStub(CurrentUserProviderInterface::class);
        $this->currentUserProvider->method('currentUserId')->willReturn('user-1');

        $this->eventBus = $this->createStub(EventBusInterface::class);
        $this->eventBus->method('publish')->willReturnCallback(function (object ...$events): void {
            foreach ($events as $event) {
                $this->publishedEvents[] = $event;
            }
        });

        $this->catalogue->add(
            $this->record(1, '9782344036075'),
            $this->record(2, '9782344036082'),
            $this->record(3, null),
        );
    }

    protected function record(int $number, ?string $isbn): CatalogueRecord
    {
        return new CatalogueRecord(
            workTitle: 'Berserk',
            headQualifier: 'Prestige',
            volumeNumber: $number,
            trailingQualifier: null,
            publisher: 'Glénat',
            author: 'Kentaro Miura',
            isbn: Isbn::tryFrom($isbn),
            coverUrl: null,
            source: 'bnf',
        );
    }

    /** @return list<class-string> */
    protected function publishedEventClasses(): array
    {
        return array_map(static fn (object $event): string => $event::class, $this->publishedEvents);
    }
}
