<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Auth\Domain\User;
use App\Manga\Domain\Exception\TooManyVolumesException;
use App\Manga\Domain\Exception\VolumeAlreadyExistsException;
use App\Manga\Domain\GenreEnum;
use App\Manga\Domain\Manga;
use App\Manga\Domain\Volume;
use PHPUnit\Framework\TestCase;

final class MangaTest extends TestCase
{
    private function makeManga(string $id = 'manga-1'): Manga
    {
        return new Manga(
            id: $id,
            title: 'One Piece',
            edition: 'Standard',
            language: 'fr',
            author: 'Oda Eiichiro',
            summary: 'A pirate adventure.',
            coverUrl: 'https://example.com/cover.jpg',
            genre: GenreEnum::Shonen,
            externalId: 'ext-123',
        );
    }

    public function testToArray(): void
    {
        $manga = $this->makeManga();
        $arr   = $manga->toArray();

        $this->assertSame('manga-1', $arr['id']);
        $this->assertSame('One Piece', $arr['title']);
        $this->assertSame('Standard', $arr['edition']);
        $this->assertSame('fr', $arr['language']);
        $this->assertSame('Oda Eiichiro', $arr['author']);
        $this->assertSame('shonen', $arr['genre']);
        $this->assertSame('ext-123', $arr['externalId']);
        $this->assertSame(0, $arr['totalVolumes']);
        $this->assertArrayHasKey('createdAt', $arr);
    }

    public function testToArrayNullableFields(): void
    {
        $manga = new Manga(id: 'm2', title: 'Test', edition: null, language: 'fr');
        $arr   = $manga->toArray();

        $this->assertNull($arr['edition']);
        $this->assertNull($arr['author']);
        $this->assertNull($arr['coverUrl']);
        $this->assertNull($arr['genre']);
        $this->assertNull($arr['externalId']);
    }

    public function testAddVolume(): void
    {
        $manga  = $this->makeManga();
        $volume = new Volume(id: 'v1', manga: $manga, number: 1, coverUrl: null, price: 7.99);
        $manga->addVolume($volume);

        $this->assertSame(1, $manga->volumes->count());
        $this->assertSame(1, $manga->toArray()['totalVolumes']);
    }

    public function testAddVolumeDuplicate(): void
    {
        $manga  = $this->makeManga();
        $volume = new Volume(id: 'v1', manga: $manga, number: 1);
        $manga->addVolume($volume);
        $manga->addVolume($volume);

        $this->assertSame(1, $manga->volumes->count());
    }

    public function testSpecialEditionDefaultsToNullAndIsExposed(): void
    {
        $standard = $this->makeManga();
        $special  = new Manga(id: 'm3', title: 'Berserk', edition: 'Glénat', language: 'fr', specialEdition: 'Prestige');

        $this->assertNull($standard->toArray()['specialEdition']);
        $this->assertSame('Prestige', $special->toArray()['specialEdition']);
    }

    public function testVolumeByNumberFindsTheVolumeOrNull(): void
    {
        $manga  = $this->makeManga();
        $volume = new Volume(id: 'v2', manga: $manga, number: 2);
        $manga->addVolume($volume);

        $this->assertSame($volume, $manga->volumeByNumber(2));
        $this->assertNull($manga->volumeByNumber(3));
    }

    public function testEnsureVolumesUpToCreatesOnlyTheMissingVolumes(): void
    {
        $manga    = $this->makeManga();
        $existing = new Volume(id: 'v2', manga: $manga, number: 2);
        $manga->addVolume($existing);

        $created = $manga->ensureVolumesUpTo(4);

        $this->assertSame([1, 3, 4], array_map(static fn (Volume $volume): int => $volume->number, $created));
        $this->assertCount(4, $manga->volumes);
        $this->assertSame($existing, $manga->volumeByNumber(2));
        $this->assertSame([], $manga->ensureVolumesUpTo(3));
    }

    public function testEnsureVolumesUpToAcceptsTheLongestSeries(): void
    {
        $manga = $this->makeManga();

        $this->assertCount(Manga::MAX_VOLUMES, $manga->ensureVolumesUpTo(Manga::MAX_VOLUMES));
    }

    /** No series has thousands of tomes: nothing is created past the bound. */
    public function testEnsureVolumesUpToRefusesAnAbsurdCount(): void
    {
        $manga = $this->makeManga();

        try {
            $manga->ensureVolumesUpTo(Manga::MAX_VOLUMES + 1);
            $this->fail('An absurd tome count must be refused.');
        } catch (TooManyVolumesException) {
            $this->assertCount(0, $manga->volumes);
        }
    }

    public function testAnotherVolumeWithTheSameNumberIsRefused(): void
    {
        $manga = $this->makeManga();
        $manga->addVolume(new Volume(id: 'v1', manga: $manga, number: 1));

        $this->expectException(VolumeAlreadyExistsException::class);
        $manga->addVolume(new Volume(id: 'v1-bis', manga: $manga, number: 1));
    }

    public function testASeriesBelongsToItsOwnerOnly(): void
    {
        $owner = $this->account('owner-1');
        $manga = new Manga(id: 'manga-owned', title: 'Berserk', edition: null, language: 'fr', owner: $owner);

        $this->assertSame($owner, $manga->owner);
        $this->assertTrue($manga->isOwnedBy('owner-1'));
        $this->assertFalse($manga->isOwnedBy('someone-else'));
    }

    public function testASeriesWithoutOwnerBelongsToNobody(): void
    {
        $manga = $this->makeManga();

        $this->assertNull($manga->owner);
        $this->assertFalse($manga->isOwnedBy('owner-1'));
    }

    private function account(string $id): User
    {
        return new User(id: $id, email: $id . '@example.com', passwordHash: 'hash', displayName: $id);
    }
}
