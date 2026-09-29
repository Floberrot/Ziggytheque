<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Service;

use App\Manga\Domain\Catalogue\CatalogueEdition;
use App\Manga\Domain\Catalogue\CatalogueRecord;
use App\Manga\Domain\Catalogue\CatalogueVolume;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\Service\CatalogueEditionAssembler;
use App\Manga\Domain\Service\PublisherNormalizer;
use PHPUnit\Framework\TestCase;

final class CatalogueEditionAssemblerTest extends TestCase
{
    private CatalogueEditionAssembler $assembler;

    protected function setUp(): void
    {
        $this->assembler = new CatalogueEditionAssembler(new PublisherNormalizer());
    }

    private function record(
        string $work,
        ?int $number,
        ?string $headQualifier = null,
        ?string $trailingQualifier = null,
        ?string $publisher = 'Glénat (Grenoble)',
        ?string $isbn = null,
        ?string $coverUrl = null,
        ?string $author = null,
    ): CatalogueRecord {
        return new CatalogueRecord(
            workTitle: $work,
            headQualifier: $headQualifier,
            volumeNumber: $number,
            trailingQualifier: $trailingQualifier,
            publisher: $publisher,
            author: $author,
            isbn: Isbn::tryFrom($isbn),
            coverUrl: $coverUrl,
            source: 'bnf',
        );
    }

    /** @param list<CatalogueEdition> $editions */
    private function labels(array $editions): array
    {
        return array_map(
            static fn (CatalogueEdition $edition): string => sprintf(
                '%s|%s|%s|%d',
                $edition->workTitle,
                $edition->publisher ?? '-',
                $edition->specialEdition ?? '-',
                $edition->volumeCount,
            ),
            $editions,
        );
    }

    public function testGroupsVolumesByWorkPublisherAndSpecialEdition(): void
    {
        $editions = $this->assembler->assemble([
            $this->record('Berserk', 1),
            $this->record('Berserk', 3, publisher: 'Glénat'),
            $this->record('Berserk', 1, headQualifier: 'Prestige'),
            $this->record('Berserk', 2, headQualifier: 'prestige'),
        ], 'berserk');

        $this->assertSame(['Berserk|Glénat|-|3', 'Berserk|Glénat|Prestige|2'], $this->labels($editions));
        $this->assertSame([1, 3], array_map(
            static fn (CatalogueVolume $volume): int => $volume->number,
            $editions[0]->volumes,
        ));
    }

    public function testTrailingQualifierSharedAcrossTomesIsTheSpecialEdition(): void
    {
        $editions = $this->assembler->assemble([
            $this->record('Berserk', 1, trailingQualifier: 'Édition prestige'),
            $this->record('Berserk', 2, trailingQualifier: 'Édition prestige'),
        ]);

        $this->assertSame(['Berserk|Glénat|Édition prestige|2'], $this->labels($editions));
    }

    public function testTrailingQualifierSeenOnOneTomeIsAVolumeTitle(): void
    {
        $editions = $this->assembler->assemble([
            $this->record('Berserk', 1, trailingQualifier: "L'épée du chevalier noir"),
            $this->record('Berserk', 2, trailingQualifier: "L'élu"),
        ]);

        $this->assertSame(['Berserk|Glénat|-|2'], $this->labels($editions));
    }

    public function testKeepsTheIsbnAndCoverOfEveryRecordOfATome(): void
    {
        $editions = $this->assembler->assemble([
            $this->record('Berserk', 1, coverUrl: 'https://covers.example/1.jpg'),
            $this->record('Berserk', 1, isbn: '9782723425483', author: 'Kentaro Miura'),
            $this->record('Berserk', 2, coverUrl: 'https://covers.example/2.jpg'),
        ]);

        $firstVolume = $editions[0]->volumes[0];
        $this->assertSame('9782723425483', $firstVolume->isbn?->value);
        $this->assertSame('https://covers.example/1.jpg', $firstVolume->coverUrl);
        $this->assertSame('https://covers.example/1.jpg', $editions[0]->coverUrl);
        $this->assertSame('Kentaro Miura', $editions[0]->author);
    }

    public function testAOneShotWithoutNumberBecomesASingleVolumeEdition(): void
    {
        $editions = $this->assembler->assemble([
            $this->record('Berserk', null, headQualifier: 'Illustrations file', isbn: '9782344036075'),
        ]);

        $this->assertSame(['Berserk|Glénat|Illustrations file|1'], $this->labels($editions));
        $this->assertSame('9782344036075', $editions[0]->volumes[0]->isbn?->value);
    }

    public function testTheSearchedWorkComesFirstThenStandardBeforeSpecial(): void
    {
        $editions = $this->assembler->assemble([
            $this->record('Berserk of Gluttony', 1, publisher: 'Delcourt'),
            $this->record('Berserk', 1, headQualifier: 'Deluxe'),
            $this->record('Berserk', 1),
            $this->record('Berserk', 2),
        ], 'Berserk');

        $this->assertSame(
            ['Berserk|Glénat|-|2', 'Berserk|Glénat|Deluxe|1', 'Berserk of Gluttony|Delcourt/Tonkam|-|1'],
            $this->labels($editions),
        );
    }

    public function testNoRecordsGiveNoEditions(): void
    {
        $this->assertSame([], $this->assembler->assemble([]));
    }
}
