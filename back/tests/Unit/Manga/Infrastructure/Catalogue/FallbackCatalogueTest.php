<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure\Catalogue;

use App\Manga\Domain\Catalogue\CatalogueRecord;
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
