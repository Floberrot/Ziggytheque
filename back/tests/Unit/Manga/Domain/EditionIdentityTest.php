<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\EditionIdentity;
use App\Manga\Domain\Manga;
use App\Manga\Domain\Service\PublisherNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EditionIdentityTest extends TestCase
{
    private PublisherNormalizer $publisherNormalizer;

    protected function setUp(): void
    {
        $this->publisherNormalizer = new PublisherNormalizer();
    }

    public function testSpellingVariantsOfTheSameSeriesMatch(): void
    {
        $catalogue = new EditionIdentity('Berserk', 'Glénat (Grenoble)', 'prestige');
        $stored    = new EditionIdentity('BERSERK', 'Glénat', 'Prestige');

        $this->assertTrue($catalogue->matches($stored, $this->publisherNormalizer));
        $this->assertSame($catalogue->key($this->publisherNormalizer), $stored->key($this->publisherNormalizer));
    }

    /** @return iterable<string, array{string, string}> */
    public static function editionSpellings(): iterable
    {
        yield 'bare name vs edition statement' => ['Prestige', 'Édition prestige'];
        yield 'abbreviated statement' => ['Édition prestige', 'Éd. prestige'];
        yield 'english word order' => ['Perfect', 'Perfect edition'];
    }

    #[DataProvider('editionSpellings')]
    public function testTheWordEditionAroundTheNameDoesNotMakeAnotherSeries(string $left, string $right): void
    {
        $this->assertTrue(
            (new EditionIdentity('Berserk', 'Glénat', $left))
                ->matches(new EditionIdentity('Berserk', 'Glénat', $right), $this->publisherNormalizer),
        );
    }

    public function testAnEditionNamedOnlyEditionStaysASpecialEdition(): void
    {
        $this->assertFalse(
            (new EditionIdentity('Berserk', 'Glénat', 'Édition'))
                ->matches(new EditionIdentity('Berserk', 'Glénat', null), $this->publisherNormalizer),
        );
    }

    public function testASpecialEditionIsAnotherSeries(): void
    {
        $standard = new EditionIdentity('Berserk', 'Glénat', null);
        $prestige = new EditionIdentity('Berserk', 'Glénat', 'Prestige');

        $this->assertFalse($standard->matches($prestige, $this->publisherNormalizer));
    }

    public function testAnotherPublisherIsAnotherSeries(): void
    {
        $this->assertFalse(
            (new EditionIdentity('Berserk', 'Glénat', null))
                ->matches(new EditionIdentity('Berserk', 'Kana', null), $this->publisherNormalizer),
        );
    }

    public function testOfMangaReadsTheStoredSeries(): void
    {
        $identity = EditionIdentity::ofManga(
            new Manga(id: 'm1', title: 'Berserk', edition: 'Glénat', language: 'fr', specialEdition: 'Prestige'),
        );

        $this->assertSame('Berserk', $identity->workTitle);
        $this->assertSame('Glénat', $identity->publisher);
        $this->assertSame('Prestige', $identity->specialEdition);
    }
}
