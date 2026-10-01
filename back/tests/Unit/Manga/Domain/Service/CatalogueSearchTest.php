<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Service;

use App\Manga\Domain\Catalogue\CatalogueRecord;
use App\Manga\Domain\Catalogue\CatalogueSearchModeEnum;
use App\Manga\Domain\EditionIdentity;
use App\Manga\Domain\Exception\CatalogueQueryTooShortException;
use App\Manga\Domain\Exception\InvalidIsbnException;
use App\Manga\Domain\Exception\IsbnNotInCatalogueException;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\Service\CatalogueEditionAssembler;
use App\Manga\Domain\Service\CatalogueSearch;
use App\Manga\Domain\Service\CatalogueTitleParser;
use App\Manga\Domain\Service\PublisherNormalizer;
use App\Tests\Doubles\Manga\InMemoryCatalogue;
use PHPUnit\Framework\TestCase;

final class CatalogueSearchTest extends TestCase
{
    private InMemoryCatalogue $catalogue;
    private CatalogueSearch $search;

    protected function setUp(): void
    {
        $publisherNormalizer = new PublisherNormalizer();
        $this->catalogue = new InMemoryCatalogue();
        $this->search = new CatalogueSearch(
            $this->catalogue,
            new CatalogueTitleParser(),
            new CatalogueEditionAssembler($publisherNormalizer),
            $publisherNormalizer,
        );

        $this->catalogue->add(
            $this->record('Berserk', 1, null, '9782723425483'),
            $this->record('Berserk', 2, null, '9782723425490'),
            $this->record('Berserk', 1, 'Prestige', '9782344036075'),
            $this->record('Berserk', 2, 'Prestige', '9782344036082'),
            $this->record('Berserk', 3, 'Prestige', null),
        );
    }

    private function record(string $work, int $number, ?string $specialEdition, ?string $isbn): CatalogueRecord
    {
        return new CatalogueRecord(
            workTitle: $work,
            headQualifier: $specialEdition,
            volumeNumber: $number,
            trailingQualifier: null,
            publisher: 'Glénat',
            author: 'Kentaro Miura',
            isbn: Isbn::tryFrom($isbn),
            coverUrl: null,
            source: 'bnf',
        );
    }

    public function testTitleSearchReturnsEverySeriesAndTheRequestedTome(): void
    {
        $result = $this->search->search('berserk 2', CatalogueSearchModeEnum::Title);

        $this->assertSame('berserk', $result->searchedText);
        $this->assertSame(2, $result->requestedVolume);
        $this->assertCount(2, $result->editions);
        $this->assertNull($result->editions[0]->specialEdition);
        $this->assertSame('Prestige', $result->editions[1]->specialEdition);
        $this->assertSame(3, $result->editions[1]->volumeCount);
    }

    public function testAuthorSearchReturnsTheAuthorsSeries(): void
    {
        $result = $this->search->search('Miura', CatalogueSearchModeEnum::Author);

        $this->assertSame('Miura', $result->searchedText);
        $this->assertNull($result->requestedVolume);
        $this->assertCount(2, $result->editions);
    }

    public function testIsbnSearchPlacesTheBookInItsSeries(): void
    {
        $result = $this->search->search('978-2-344-03608-2', CatalogueSearchModeEnum::Isbn);

        $this->assertSame(2, $result->requestedVolume);
        $this->assertCount(1, $result->editions);
        $this->assertSame('Prestige', $result->editions[0]->specialEdition);
        $this->assertSame(3, $result->editions[0]->volumeCount);
    }

    public function testTooShortQueryIsRejected(): void
    {
        $this->expectException(CatalogueQueryTooShortException::class);

        $this->search->search(' b ', CatalogueSearchModeEnum::Title);
    }

    public function testInvalidIsbnIsRejected(): void
    {
        $this->expectException(InvalidIsbnException::class);

        $this->search->search('not-an-isbn', CatalogueSearchModeEnum::Isbn);
    }

    public function testIdentifyIsbnFailsForAnUnknownBook(): void
    {
        $this->expectException(IsbnNotInCatalogueException::class);

        $this->search->identifyIsbn(Isbn::fromString('9782811645632'));
    }

    public function testIdentifyIsbnReturnsTheEditionAndTheTome(): void
    {
        $identification = $this->search->identifyIsbn(Isbn::fromString('9782723425490'));

        $this->assertNull($identification->edition->specialEdition);
        $this->assertSame(2, $identification->volume->number);
    }

    public function testIdentifyIsbnFindsTheTomeWhenItsRecordStatesAnotherIsbn(): void
    {
        // The record found by 9782344050002 lists only the ISBN of another binding.
        $this->catalogue->answerIsbn('9782344050002', $this->record('Berserk', 5, 'Prestige', '9782344061008'));

        $identification = $this->search->identifyIsbn(Isbn::fromString('9782344050002'));

        $this->assertSame('Prestige', $identification->edition->specialEdition);
        $this->assertSame(5, $identification->volume->number);
        $this->assertSame('9782344050002', $identification->volume->isbn?->value);
    }

    public function testEditionReturnsTheMatchingSeriesWithAllItsTomes(): void
    {
        $edition = $this->search->edition(new EditionIdentity('Berserk', 'Glénat (Grenoble)', 'prestige'));

        $this->assertNotNull($edition);
        $this->assertSame('Prestige', $edition->specialEdition);
        $this->assertCount(3, $edition->volumes);
    }

    public function testEditionReturnsNullWhenTheSeriesIsUnknown(): void
    {
        $this->assertNull($this->search->edition(new EditionIdentity('Berserk', 'Kana', null)));
    }

    public function testEditionRejectsATooShortTitle(): void
    {
        $this->expectException(CatalogueQueryTooShortException::class);

        $this->search->edition(new EditionIdentity('B', null, null));
    }
}
