<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Service;

use App\Manga\Domain\ExternalMangaDto;
use App\Manga\Domain\GenreEnum;
use App\Manga\Domain\Manga;
use App\Manga\Domain\Service\MangaEnricher;
use PHPUnit\Framework\TestCase;

final class MangaEnricherTest extends TestCase
{
    private MangaEnricher $enricher;

    protected function setUp(): void
    {
        $this->enricher = new MangaEnricher();
    }

    private function candidate(string $title, ?string $genre = 'seinen', ?string $summary = 'Guts…', ?string $author = 'Miura, Kentarou'): ExternalMangaDto
    {
        return new ExternalMangaDto(
            externalId: '2',
            title: $title,
            edition: null,
            author: $author,
            summary: $summary,
            coverUrl: null,
            genre: $genre,
            language: 'fr',
            source: 'jikan',
        );
    }

    public function testFillsTheMissingFieldsFromTheSameTitle(): void
    {
        $manga = new Manga(id: 'm1', title: 'Berserk', edition: 'Glénat', language: 'fr');

        $changed = $this->enricher->enrich($manga, [$this->candidate('Berserk: The Prototype'), $this->candidate('BERSERK')]);

        $this->assertTrue($changed);
        $this->assertSame(GenreEnum::Seinen, $manga->genre);
        $this->assertSame('Guts…', $manga->summary);
        $this->assertSame('Miura, Kentarou', $manga->author);
    }

    public function testNeverOverwritesWhatIsAlreadySet(): void
    {
        $manga = new Manga(
            id: 'm1',
            title: 'Berserk',
            edition: 'Glénat',
            language: 'fr',
            author: 'Kentaro Miura',
            summary: 'Résumé',
            genre: GenreEnum::Fantasy,
        );

        $this->assertFalse($this->enricher->needsEnrichment($manga));
        $this->assertFalse($this->enricher->enrich($manga, [$this->candidate('Berserk')]));
        $this->assertSame(GenreEnum::Fantasy, $manga->genre);
        $this->assertSame('Résumé', $manga->summary);
        $this->assertSame('Kentaro Miura', $manga->author);
    }

    public function testIgnoresCandidatesWithAnotherTitle(): void
    {
        $manga = new Manga(id: 'm1', title: "L'Attaque des Titans", edition: 'Pika', language: 'fr');

        $this->assertTrue($this->enricher->needsEnrichment($manga));
        $this->assertFalse($this->enricher->enrich($manga, [$this->candidate('Shingeki no Kyojin')]));
        $this->assertNull($manga->genre);
    }

    public function testSkipsAnUnknownGenreAndEmptyTexts(): void
    {
        $manga = new Manga(id: 'm1', title: 'Berserk', edition: 'Glénat', language: 'fr');

        $changed = $this->enricher->enrich($manga, [$this->candidate('Berserk', genre: 'unknown', summary: '', author: null)]);

        $this->assertFalse($changed);
        $this->assertNull($manga->genre);
        $this->assertNull($manga->summary);
        $this->assertNull($manga->author);
    }
}
