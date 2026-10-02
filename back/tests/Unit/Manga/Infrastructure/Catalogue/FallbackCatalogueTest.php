<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure\Catalogue;

use App\Manga\Domain\Catalogue\CatalogueInterface;
use App\Manga\Domain\Catalogue\CatalogueRecord;
use App\Manga\Domain\Exception\CatalogueUnavailableException;
use App\Manga\Domain\Isbn;
use App\Manga\Infrastructure\Catalogue\FallbackCatalogue;
use App\Tests\Doubles\Manga\InMemoryCatalogue;
use PHPUnit\Framework\TestCase;

final class FallbackCatalogueTest extends TestCase
{
    private function record(string $source): CatalogueRecord
    {
        return new CatalogueRecord('Berserk', null, 1, null, 'Glénat', 'Kentaro Miura', Isbn::fromString('9782723425483'), null, $source);
    }

    public function testThePrimaryAnswersWhenItHasRecords(): void
    {
        $primary  = new InMemoryCatalogue();
        $fallback = new InMemoryCatalogue();
        $primary->add($this->record('bnf'));
        $fallback->add($this->record('google_books'));

        $catalogue = new FallbackCatalogue($primary, $fallback);

        $this->assertSame('bnf', $catalogue->searchByTitle('Berserk')[0]->source);
        $this->assertSame('bnf', $catalogue->searchByAuthor('Miura')[0]->source);
        $this->assertSame('bnf', $catalogue->findByIsbn(Isbn::fromString('9782723425483'))[0]->source);
    }

    private function downCatalogue(string $source): CatalogueInterface
    {
        return new class ($source) implements CatalogueInterface {
            public function __construct(private readonly string $source)
            {
            }

            public function searchByTitle(string $title): array
            {
                throw new CatalogueUnavailableException($this->source);
            }

            public function searchByAuthor(string $author): array
            {
                throw new CatalogueUnavailableException($this->source);
            }

            public function findByIsbn(Isbn $isbn): array
            {
                throw new CatalogueUnavailableException($this->source);
            }
        };
    }

    public function testTheFallbackAnswersWhileThePrimaryIsDown(): void
    {
        $fallback = new InMemoryCatalogue();
        $fallback->add($this->record('google_books'));

        $catalogue = new FallbackCatalogue($this->downCatalogue('BnF'), $fallback);

        $this->assertSame('google_books', $catalogue->searchByTitle('Berserk')[0]->source);
    }

    /** BnF down and Google empty: the book may exist, so this is no "not found". */
    public function testAPrimaryOutageWithAnEmptyFallbackStaysAnOutage(): void
    {
        $catalogue = new FallbackCatalogue($this->downCatalogue('BnF'), new InMemoryCatalogue());

        $this->expectException(CatalogueUnavailableException::class);
        $this->expectExceptionMessage('BnF');
        $catalogue->findByIsbn(Isbn::fromString('9782723425483'));
    }

    public function testBothDownIsAnOutage(): void
    {
        $catalogue = new FallbackCatalogue($this->downCatalogue('BnF'), $this->downCatalogue('Google Books'));

        $this->expectException(CatalogueUnavailableException::class);
        $catalogue->searchByAuthor('Miura');
    }

    /** The BnF answered "nothing": a Google outage cannot change that answer. */
    public function testAnEmptyPrimaryWithTheFallbackDownFindsNothing(): void
    {
        $catalogue = new FallbackCatalogue(new InMemoryCatalogue(), $this->downCatalogue('Google Books'));

        $this->assertSame([], $catalogue->searchByTitle('Berserk'));
    }

    public function testTheFallbackAnswersWhenThePrimaryHasNothing(): void
    {
        $fallback = new InMemoryCatalogue();
        $fallback->add($this->record('google_books'));

        $catalogue = new FallbackCatalogue(new InMemoryCatalogue(), $fallback);

        $this->assertSame('google_books', $catalogue->searchByTitle('Berserk')[0]->source);
        $this->assertSame('google_books', $catalogue->searchByAuthor('Miura')[0]->source);
        $this->assertSame('google_books', $catalogue->findByIsbn(Isbn::fromString('9782723425483'))[0]->source);
    }
}
