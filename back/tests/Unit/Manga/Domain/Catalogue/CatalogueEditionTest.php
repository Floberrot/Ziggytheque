<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Catalogue;

use App\Manga\Domain\Catalogue\CatalogueEdition;
use App\Manga\Domain\Catalogue\CatalogueVolume;
use App\Manga\Domain\Isbn;
use PHPUnit\Framework\TestCase;

final class CatalogueEditionTest extends TestCase
{
    private function makeEdition(): CatalogueEdition
    {
        return new CatalogueEdition(
            workTitle: 'Berserk',
            publisher: 'Glénat',
            specialEdition: 'Prestige',
            author: 'Kentaro Miura',
            coverUrl: 'https://covers.example/1.jpg',
            volumeCount: 2,
            volumes: [
                new CatalogueVolume(1, Isbn::fromString('9782723425483'), 'https://covers.example/1.jpg'),
                new CatalogueVolume(2, null, null),
            ],
        );
    }

    public function testIdentityCarriesWorkPublisherAndSpecialEdition(): void
    {
        $identity = $this->makeEdition()->identity();

        $this->assertSame('Berserk', $identity->workTitle);
        $this->assertSame('Glénat', $identity->publisher);
        $this->assertSame('Prestige', $identity->specialEdition);
    }

    public function testVolumeByIsbnFindsTheTomeOrNull(): void
    {
        $edition = $this->makeEdition();

        $this->assertSame(1, $edition->volumeByIsbn(Isbn::fromString('978-2-7234-2548-3'))?->number);
        $this->assertNull($edition->volumeByIsbn(Isbn::fromString('9782344036075')));
    }

    public function testToArray(): void
    {
        $this->assertSame([
            'workTitle'      => 'Berserk',
            'publisher'      => 'Glénat',
            'specialEdition' => 'Prestige',
            'author'         => 'Kentaro Miura',
            'coverUrl'       => 'https://covers.example/1.jpg',
            'volumeCount'    => 2,
            'volumes'        => [
                ['number' => 1, 'isbn' => '9782723425483', 'coverUrl' => 'https://covers.example/1.jpg'],
                ['number' => 2, 'isbn' => null, 'coverUrl' => null],
            ],
        ], $this->makeEdition()->toArray());
    }
}
