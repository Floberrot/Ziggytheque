<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\SearchVolumeExternal;

use App\Manga\Application\SearchVolumeExternal\SearchVolumeExternalHandler;
use App\Manga\Application\SearchVolumeExternal\SearchVolumeExternalQuery;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Domain\MultiContextCoverProviderInterface;
use App\Manga\Domain\MultiSourceCoverProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class SearchVolumeExternalHandlerTest extends TestCase
{
    private function cover(string $source, string $url = 'https://example.test/cover.jpg'): MangaVolumeCoverDto
    {
        return new MangaVolumeCoverDto(coverUrl: $url, spineUrl: null, isbn: null, source: $source);
    }

    /** @param list<MangaVolumeCoverDto> $covers */
    private function multiSource(array $covers): MultiSourceCoverProviderInterface
    {
        return new class ($covers) implements MultiSourceCoverProviderInterface {
            /** @param list<MangaVolumeCoverDto> $covers */
            public function __construct(private array $covers)
            {
            }

            public function findAllByIsbn(Isbn $isbn): array
            {
                return [];
            }

            public function findAllByContext(string $mangaTitle, ?string $edition, int $volumeNumber, string $language = 'fr'): array
            {
                return $this->covers;
            }
        };
    }

    /** @param list<MangaVolumeCoverDto> $covers */
    private function contextProvider(array $covers): MultiContextCoverProviderInterface
    {
        return new class ($covers) implements MultiContextCoverProviderInterface {
            /** @param list<MangaVolumeCoverDto> $covers */
            public function __construct(private array $covers)
            {
            }

            public function findAllByContext(string $mangaTitle, ?string $edition, int $volumeNumber, string $language = 'fr'): array
            {
                return $this->covers;
            }
        };
    }

    /** @param array<string, MultiContextCoverProviderInterface> $services */
    private function locator(array $services): ContainerInterface
    {
        return new class ($services) implements ContainerInterface {
            /** @param array<string, MultiContextCoverProviderInterface> $services */
            public function __construct(private array $services)
            {
            }

            public function get(string $id): mixed
            {
                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }

    public function testCompositeProviderMergesEverySourceWithProvenance(): void
    {
        $handler = new SearchVolumeExternalHandler(
            $this->multiSource([$this->cover('mangadex'), $this->cover('google_books')]),
            $this->locator([]),
        );

        $results = $handler(new SearchVolumeExternalQuery('One Piece', volumeNumber: 1));

        $this->assertCount(2, $results);
        $this->assertSame('mangadex', $results[0]['source']);
        $this->assertSame('google_books', $results[1]['source']);
    }

    public function testSpecificProviderNarrowsToThatSingleSource(): void
    {
        $handler = new SearchVolumeExternalHandler(
            $this->multiSource([$this->cover('mangadex'), $this->cover('google_books')]),
            $this->locator(['googlebooks' => $this->contextProvider([$this->cover('google_books')])]),
        );

        $results = $handler(new SearchVolumeExternalQuery('One Piece', volumeNumber: 1, provider: 'googlebooks'));

        $this->assertCount(1, $results);
        $this->assertSame('google_books', $results[0]['source']);
    }

    public function testUnknownProviderFallsBackToCompositeMerge(): void
    {
        $handler = new SearchVolumeExternalHandler(
            $this->multiSource([$this->cover('mangadex')]),
            $this->locator([]),
        );

        $results = $handler(new SearchVolumeExternalQuery('One Piece', volumeNumber: 1, provider: 'does-not-exist'));

        $this->assertCount(1, $results);
        $this->assertSame('mangadex', $results[0]['source']);
    }

    public function testMapsCoverDtoToArrayShape(): void
    {
        $handler = new SearchVolumeExternalHandler(
            $this->multiSource([$this->cover('mangadex', 'https://cdn.test/c.jpg')]),
            $this->locator([]),
        );

        $results = $handler(new SearchVolumeExternalQuery('Berserk', volumeNumber: 3));

        $this->assertSame([
            'externalId' => null,
            'title' => 'Berserk',
            'edition' => null,
            'coverUrl' => 'https://cdn.test/c.jpg',
            'spineUrl' => null,
            'isbn' => null,
            'language' => 'fr',
            'totalVolumes' => null,
            'source' => 'mangadex',
        ], $results[0]);
    }

    public function testDefaultsToVolumeOneWhenNotProvided(): void
    {
        $handler = new SearchVolumeExternalHandler(
            $this->multiSource([$this->cover('mangadex')]),
            $this->locator([]),
        );

        $results = $handler(new SearchVolumeExternalQuery('One Piece'));

        $this->assertCount(1, $results);
    }
}
