<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure\Catalogue;

use App\Manga\Domain\Catalogue\CatalogueInterface;
use App\Manga\Domain\Catalogue\CatalogueRecord;
use App\Manga\Domain\Isbn;
use App\Manga\Infrastructure\Catalogue\CachedCatalogue;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CachedCatalogueTest extends TestCase
{
    private function countingCatalogue(array $records): CatalogueInterface
    {
        return new class ($records) implements CatalogueInterface {
            public int $calls = 0;

            /** @param list<CatalogueRecord> $records */
            public function __construct(private readonly array $records)
            {
            }

            public function searchByTitle(string $title): array
            {
                $this->calls++;

                return $this->records;
            }

            public function searchByAuthor(string $author): array
            {
                $this->calls++;

                return $this->records;
            }

            public function findByIsbn(Isbn $isbn): array
            {
                $this->calls++;

                return $this->records;
            }
        };
    }

    public function testRepeatedSearchesAreServedFromTheCache(): void
    {
        $record = new CatalogueRecord('Berserk', null, 1, null, 'Glénat', null, null, null, 'bnf');
        $inner  = $this->countingCatalogue([$record]);
        $cached = new CachedCatalogue($inner, new ArrayAdapter());

        $cached->searchByTitle('Berserk');
        $cached->searchByTitle('berserk ');
        $cached->searchByAuthor('Miura');
        $cached->searchByAuthor('Miura');
        $cached->findByIsbn(Isbn::fromString('9782723425483'));
        $result = $cached->findByIsbn(Isbn::fromString('9782723425483'));

        $this->assertSame(3, $inner->calls);
        $this->assertEquals([$record], $result);
    }

    public function testABlankTermNeverReachesTheCatalogue(): void
    {
        $inner  = $this->countingCatalogue([]);
        $cached = new CachedCatalogue($inner, new ArrayAdapter());

        $this->assertSame([], $cached->searchByTitle(' - '));
        $this->assertSame(0, $inner->calls);
    }
}
