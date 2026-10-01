<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Catalogue;

use App\Manga\Domain\Catalogue\CatalogueRecord;
use App\Manga\Domain\Isbn;
use PHPUnit\Framework\TestCase;

final class CatalogueRecordTest extends TestCase
{
    public function testWithIsbnKeepsEverythingElse(): void
    {
        $record = new CatalogueRecord(
            workTitle: 'Berserk',
            headQualifier: 'Prestige',
            volumeNumber: 5,
            trailingQualifier: "L'éclipse",
            publisher: 'Glénat',
            author: 'Kentaro Miura',
            isbn: null,
            coverUrl: 'https://covers.example/5.jpg',
            source: 'bnf',
        );

        $withIsbn = $record->withIsbn(Isbn::fromString('9782344050002'));

        $this->assertSame('9782344050002', $withIsbn->isbn?->value);
        $this->assertNull($record->isbn, 'The original record is left untouched.');
        $this->assertSame(
            ['Berserk', 'Prestige', 5, "L'éclipse", 'Glénat', 'Kentaro Miura', 'https://covers.example/5.jpg', 'bnf'],
            [
                $withIsbn->workTitle, $withIsbn->headQualifier, $withIsbn->volumeNumber, $withIsbn->trailingQualifier,
                $withIsbn->publisher, $withIsbn->author, $withIsbn->coverUrl, $withIsbn->source,
            ],
        );
    }
}
