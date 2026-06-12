<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\UploadVolumeFace;

use App\Manga\Application\UploadVolumeFace\UploadVolumeFaceCommand;
use App\Manga\Application\UploadVolumeFace\UploadVolumeFaceHandler;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Volume;
use App\Manga\Domain\VolumeFace;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Doubles\Manga\InMemoryImageStorage;
use PHPUnit\Framework\TestCase;

final class UploadVolumeFaceHandlerTest extends TestCase
{
    /**
     * @return array{UploadVolumeFaceHandler, Manga, InMemoryImageStorage}
     */
    private function make(bool $withVolume = true): array
    {
        $manga = new Manga(id: 'm1', title: 'Berserk', edition: null, language: 'fr');
        if ($withVolume) {
            $manga->addVolume(new Volume(id: 'v1', manga: $manga, number: 1));
        }

        $repository = new class ($manga) implements MangaRepositoryInterface {
            public bool $saved = false;

            public function __construct(private Manga $manga)
            {
            }

            public function findById(string $id): ?Manga
            {
                return $this->manga->id === $id ? $this->manga : null;
            }

            public function search(string $query): array
            {
                return [];
            }

            public function findAllPaginated(int $offset, int $limit): array
            {
                return [];
            }

            public function countAll(): int
            {
                return 0;
            }

            public function save(Manga $manga): void
            {
                $this->saved = true;
            }
        };

        $storage = new InMemoryImageStorage();

        return [new UploadVolumeFaceHandler($repository, $storage), $manga, $storage];
    }

    public function testStoresImageAndSetsBackCoverUrl(): void
    {
        [$handler, $manga, $storage] = $this->make();

        $handler(new UploadVolumeFaceCommand('m1', 'v1', VolumeFace::Back, base64_encode('PNGBYTES'), 'image/png'));

        /** @var Volume $volume */
        $volume = $manga->volumes->first();
        $this->assertCount(1, $storage->stored);
        $key = array_key_first($storage->stored);
        $this->assertStringStartsWith('volume-faces/v1-back-', (string) $key);
        $this->assertStringEndsWith('.png', (string) $key);
        $this->assertSame('PNGBYTES', $storage->stored[$key]);
        $this->assertSame('https://storage.test/' . $key, $volume->backCoverUrl);
        $this->assertNull($volume->coverUrl);
        $this->assertNull($volume->spineUrl);
    }

    public function testCoverAndSpineFacesTargetTheRightProperty(): void
    {
        [$handlerCover, $mangaCover] = $this->make();
        $handlerCover(new UploadVolumeFaceCommand('m1', 'v1', VolumeFace::Cover, base64_encode('x'), 'image/jpeg'));
        /** @var Volume $coverVolume */
        $coverVolume = $mangaCover->volumes->first();
        $this->assertNotNull($coverVolume->coverUrl);
        $this->assertNull($coverVolume->backCoverUrl);

        [$handlerSpine, $mangaSpine] = $this->make();
        $handlerSpine(new UploadVolumeFaceCommand('m1', 'v1', VolumeFace::Spine, base64_encode('x'), 'image/jpeg'));
        /** @var Volume $spineVolume */
        $spineVolume = $mangaSpine->volumes->first();
        $this->assertNotNull($spineVolume->spineUrl);
        $this->assertNull($spineVolume->coverUrl);
    }

    public function testStripsDataUrlPrefixBeforeDecoding(): void
    {
        [$handler, , $storage] = $this->make();

        $handler(new UploadVolumeFaceCommand(
            'm1',
            'v1',
            VolumeFace::Cover,
            'data:image/png;base64,' . base64_encode('RAW'),
            'image/png',
        ));

        $this->assertSame('RAW', $storage->stored[array_key_first($storage->stored)]);
    }

    public function testThrowsWhenMangaMissing(): void
    {
        [$handler] = $this->make(withVolume: false);

        $this->expectException(NotFoundException::class);
        $handler(new UploadVolumeFaceCommand('unknown', 'v1', VolumeFace::Cover, base64_encode('x'), 'image/jpeg'));
    }

    public function testThrowsWhenVolumeMissing(): void
    {
        [$handler] = $this->make(withVolume: false);

        $this->expectException(NotFoundException::class);
        $handler(new UploadVolumeFaceCommand('m1', 'absent', VolumeFace::Cover, base64_encode('x'), 'image/jpeg'));
    }
}
