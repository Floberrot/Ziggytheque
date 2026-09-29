<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Domain\Service;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\Service\CatalogueCollectionMatcher;
use App\Collection\Domain\VolumeEntry;
use App\Manga\Domain\Catalogue\CatalogueEdition;
use App\Manga\Domain\EditionIdentity;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Service\PublisherNormalizer;
use PHPUnit\Framework\TestCase;

final class CatalogueCollectionMatcherTest extends TestCase
{
    private Manga $standard;
    private Manga $prestige;
    private CatalogueCollectionMatcher $matcher;

    protected function setUp(): void
    {
        $this->standard = new Manga(id: 'm-standard', title: 'Berserk', edition: 'Glénat', language: 'fr');
        $this->prestige = new Manga(id: 'm-prestige', title: 'Berserk', edition: 'Glénat', language: 'fr', specialEdition: 'Prestige');
        $this->prestige->ensureVolumesUpTo(3);

        $entry = new CollectionEntry(id: 'ce-prestige', manga: $this->prestige);
        $entry->trackMissingVolumes();
        $entry->volumeEntryForNumber(3)?->markOwned();
        $entry->volumeEntryForNumber(1)?->markOwned();

        $mangaRepository = $this->createStub(MangaRepositoryInterface::class);
        $mangaRepository->method('findByTitles')->willReturn([$this->standard, $this->prestige]);

        $collectionRepository = $this->createStub(CollectionRepositoryInterface::class);
        $collectionRepository->method('findByMangaIds')->willReturn([$entry]);

        $this->matcher = new CatalogueCollectionMatcher($mangaRepository, $collectionRepository, new PublisherNormalizer());
    }

    private function edition(?string $publisher, ?string $specialEdition): CatalogueEdition
    {
        return new CatalogueEdition('Berserk', $publisher, $specialEdition, null, null, 3, []);
    }

    public function testFindSeriesMatchesTheIdentity(): void
    {
        $this->assertSame($this->prestige, $this->matcher->findSeries(new EditionIdentity('BERSERK', 'Glénat (Grenoble)', 'prestige')));
        $this->assertSame($this->standard, $this->matcher->findSeries(new EditionIdentity('Berserk', 'Glénat', null)));
        $this->assertNull($this->matcher->findSeries(new EditionIdentity('Berserk', 'Kana', null)));
    }

    public function testDescribeAddsTheCollectionStatusOfEachEdition(): void
    {
        $described = $this->matcher->describe([
            $this->edition('Glénat', 'Prestige'),
            $this->edition('Glénat', null),
            $this->edition('Kana', null),
        ]);

        $this->assertSame(['entryId' => 'ce-prestige', 'ownedNumbers' => [1, 3]], $described[0]['collection']);
        $this->assertNull($described[1]['collection'], 'stored series, but not in this user collection');
        $this->assertNull($described[2]['collection'], 'series never stored');
        $this->assertSame('Prestige', $described[0]['specialEdition']);
    }
}
