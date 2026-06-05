<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Service;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Domain\Service\CoverBatchResolver;
use App\Manga\Domain\Volume;
use App\Tests\Doubles\Manga\InMemoryCoverBatchProgressPublisher;
use PHPUnit\Framework\TestCase;

final class CoverBatchResolverTest extends TestCase
{
    private function makeManga(): Manga
    {
        return new Manga(id: 'manga-1', title: 'Naruto', edition: 'Kana', language: 'fr');
    }

    private function makeVolume(Manga $manga, int $number, ?string $coverUrl = null, ?Isbn $isbn = null): Volume
    {
        return new Volume(
            id: 'vol-' . $number,
            manga: $manga,
            number: $number,
            coverUrl: $coverUrl,
            isbn: $isbn,
        );
    }

    private function makeProvider(?MangaVolumeCoverDto $dto): MangaCoverProviderInterface
    {
        return new class ($dto) implements MangaCoverProviderInterface {
            public function __construct(private readonly ?MangaVolumeCoverDto $dto)
            {
            }

            public function findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto
            {
                return $this->dto;
            }

            public function findByContext(string $mangaTitle, ?string $edition, int $volumeNumber, string $language = 'fr'): ?MangaVolumeCoverDto
            {
                return $this->dto;
            }
        };
    }

    private function makeDto(string $coverUrl = 'https://example.com/cover.jpg'): MangaVolumeCoverDto
    {
        return new MangaVolumeCoverDto(
            coverUrl: $coverUrl,
            spineUrl: null,
            isbn: null,
            source: 'test',
        );
    }

    public function testSkipsVolumesWithCoverWhenForceIsFalse(): void
    {
        $manga = $this->makeManga();
        $volume = $this->makeVolume($manga, 1, 'https://existing.com/cover.jpg');
        $manga->addVolume($volume);

        $resolver = new CoverBatchResolver($this->makeProvider($this->makeDto()));
        $result = $resolver->resolveAll($manga, force: false, volumeIds: null);

        $this->assertSame(0, $result->updated);
        $this->assertSame(1, $result->skipped);
        $this->assertSame('https://existing.com/cover.jpg', $volume->coverUrl);
    }

    public function testOverwritesExistingCoverWhenForceIsTrue(): void
    {
        $manga = $this->makeManga();
        $volume = $this->makeVolume($manga, 1, 'https://old.com/cover.jpg');
        $manga->addVolume($volume);

        $resolver = new CoverBatchResolver($this->makeProvider($this->makeDto('https://new.com/cover.jpg')));
        $result = $resolver->resolveAll($manga, force: true, volumeIds: null);

        $this->assertSame(1, $result->updated);
        $this->assertSame(0, $result->skipped);
        $this->assertSame('https://new.com/cover.jpg', $volume->coverUrl);
    }

    public function testCountsFailedWhenProviderReturnsNull(): void
    {
        $manga = $this->makeManga();
        $manga->addVolume($this->makeVolume($manga, 1));
        $manga->addVolume($this->makeVolume($manga, 2));

        $resolver = new CoverBatchResolver($this->makeProvider(null));
        $result = $resolver->resolveAll($manga, force: false, volumeIds: null);

        $this->assertSame(0, $result->updated);
        $this->assertSame(2, $result->failed);
        $this->assertSame(0, $result->skipped);
    }

    public function testRestrictsToVolumeIdsWhenSpecified(): void
    {
        $manga = $this->makeManga();
        $volume1 = $this->makeVolume($manga, 1);
        $volume2 = $this->makeVolume($manga, 2);
        $manga->addVolume($volume1);
        $manga->addVolume($volume2);

        $resolver = new CoverBatchResolver($this->makeProvider($this->makeDto()));
        $result = $resolver->resolveAll($manga, force: false, volumeIds: ['vol-1']);

        $this->assertSame(1, $result->updated);
        $this->assertNotNull($volume1->coverUrl);
        $this->assertNull($volume2->coverUrl);
    }

    public function testUsesIsbnSearchWhenVolumeHasIsbn(): void
    {
        $isbn = Isbn::fromString('9782123456780');
        $manga = $this->makeManga();
        $volume = $this->makeVolume($manga, 1, null, $isbn);
        $manga->addVolume($volume);

        $dtoFromIsbn = new MangaVolumeCoverDto('https://ol.org/cover.jpg', null, $isbn, 'open_library');
        $callLog = [];

        $provider = new class ($dtoFromIsbn, $callLog) implements MangaCoverProviderInterface {
            public function __construct(
                private readonly MangaVolumeCoverDto $dto,
                private array &$log,
            ) {
            }

            public function findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto
            {
                $this->log[] = 'findByIsbn';
                return $this->dto;
            }

            public function findByContext(string $mangaTitle, ?string $edition, int $volumeNumber, string $language = 'fr'): ?MangaVolumeCoverDto
            {
                $this->log[] = 'findByContext';
                return $this->dto;
            }
        };

        $resolver = new CoverBatchResolver($provider);
        $resolver->resolveAll($manga, force: false, volumeIds: null);

        $this->assertContains('findByIsbn', $callLog);
        $this->assertNotContains('findByContext', $callLog);
    }

    public function testFallsBackToContextWhenIsbnSearchReturnsNull(): void
    {
        $isbn = Isbn::fromString('9782123456780');
        $manga = $this->makeManga();
        $volume = $this->makeVolume($manga, 1, null, $isbn);
        $manga->addVolume($volume);

        $callLog = [];
        $dtoFromContext = new MangaVolumeCoverDto('https://mangadex.org/cover.jpg', null, null, 'mangadex');

        $provider = new class ($dtoFromContext, $callLog) implements MangaCoverProviderInterface {
            public function __construct(
                private readonly MangaVolumeCoverDto $dto,
                private array &$log,
            ) {
            }

            public function findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto
            {
                $this->log[] = 'findByIsbn';
                return null;
            }

            public function findByContext(string $mangaTitle, ?string $edition, int $volumeNumber, string $language = 'fr'): ?MangaVolumeCoverDto
            {
                $this->log[] = 'findByContext';
                return $this->dto;
            }
        };

        $resolver = new CoverBatchResolver($provider);
        $result = $resolver->resolveAll($manga, force: false, volumeIds: null);

        $this->assertContains('findByIsbn', $callLog);
        $this->assertContains('findByContext', $callLog);
        $this->assertSame(1, $result->updated);
        $this->assertSame('https://mangadex.org/cover.jpg', $volume->coverUrl);
    }

    public function testPublishesProgressEventsWhenPublisherProvided(): void
    {
        $manga = $this->makeManga();
        $vol1 = $this->makeVolume($manga, 1);
        $vol2 = $this->makeVolume($manga, 2);
        $manga->addVolume($vol1);
        $manga->addVolume($vol2);

        $dtoFromContext = new MangaVolumeCoverDto('https://example.com/cover.jpg', null, null, 'test');

        $callCount = 0;
        $provider = new class ($dtoFromContext, $callCount) implements MangaCoverProviderInterface {
            public function __construct(
                private readonly MangaVolumeCoverDto $dto,
                private int &$count,
            ) {
            }

            public function findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto
            {
                return null;
            }

            public function findByContext(string $mangaTitle, ?string $edition, int $volumeNumber, string $language = 'fr'): ?MangaVolumeCoverDto
            {
                $this->count++;
                return $this->count === 1 ? $this->dto : null;
            }
        };

        $publisher = new InMemoryCoverBatchProgressPublisher();
        $resolver = new CoverBatchResolver($provider);
        $resolver->resolveAll($manga, force: false, volumeIds: null, publisher: $publisher, batchId: 'batch-1');

        $types = array_map(static fn ($event) => $event->type, $publisher->events);
        $this->assertSame(['volume_resolved', 'volume_failed'], $types);
    }

    public function testDoesNotPublishForSkippedVolumes(): void
    {
        $manga = $this->makeManga();
        $volume = $this->makeVolume($manga, 1, 'https://existing.com/cover.jpg');
        $manga->addVolume($volume);

        $publisher = new InMemoryCoverBatchProgressPublisher();
        $resolver = new CoverBatchResolver($this->makeProvider($this->makeDto()));
        $resolver->resolveAll($manga, force: false, volumeIds: null, publisher: $publisher, batchId: 'batch-1');

        $this->assertEmpty($publisher->events);
    }

    public function testCountResolvableExcludesSkippedVolumes(): void
    {
        $manga = $this->makeManga();
        $manga->addVolume($this->makeVolume($manga, 1));
        $manga->addVolume($this->makeVolume($manga, 2, 'https://existing.com/cover.jpg'));
        $manga->addVolume($this->makeVolume($manga, 3));

        $resolver = new CoverBatchResolver($this->makeProvider(null));

        $this->assertSame(2, $resolver->countResolvable($manga, force: false, volumeIds: null));
        $this->assertSame(3, $resolver->countResolvable($manga, force: true, volumeIds: null));
        $this->assertSame(1, $resolver->countResolvable($manga, force: false, volumeIds: ['vol-1']));
    }
}
