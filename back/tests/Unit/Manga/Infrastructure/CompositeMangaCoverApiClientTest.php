<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Infrastructure\ExternalApi\CompositeMangaCoverApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CompositeMangaCoverApiClientTest extends TestCase
{
    private function makeProvider(?MangaVolumeCoverDto $returnValue): MangaCoverProviderInterface
    {
        return new class ($returnValue) implements MangaCoverProviderInterface {
            public function __construct(private readonly ?MangaVolumeCoverDto $dto) {}

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

    private function makeDto(string $source): MangaVolumeCoverDto
    {
        return new MangaVolumeCoverDto(
            coverUrl: 'https://example.com/cover.jpg',
            spineUrl: null,
            isbn: null,
            source: $source,
        );
    }

    public function testReturnsFirstNonNullResult(): void
    {
        $dtoA = $this->makeDto('provider_a');
        $composite = new CompositeMangaCoverApiClient(
            [
                $this->makeProvider($dtoA),
                $this->makeProvider($this->makeDto('provider_b')),
            ],
            new NullLogger(),
        );

        $isbn = Isbn::fromString('9782123456780');
        $result = $composite->findByIsbn($isbn);

        $this->assertSame($dtoA, $result);
        $this->assertSame('provider_a', $result->source);
    }

    public function testSkipsNullResultsAndReturnsSecondProvider(): void
    {
        $dtoB = $this->makeDto('provider_b');
        $composite = new CompositeMangaCoverApiClient(
            [
                $this->makeProvider(null),
                $this->makeProvider($dtoB),
            ],
            new NullLogger(),
        );

        $isbn = Isbn::fromString('9782123456780');
        $result = $composite->findByIsbn($isbn);

        $this->assertSame($dtoB, $result);
    }

    public function testReturnsNullWhenAllProvidersReturnNull(): void
    {
        $composite = new CompositeMangaCoverApiClient(
            [
                $this->makeProvider(null),
                $this->makeProvider(null),
                $this->makeProvider(null),
            ],
            new NullLogger(),
        );

        $isbn = Isbn::fromString('9782123456780');
        $result = $composite->findByIsbn($isbn);

        $this->assertNull($result);
    }

    public function testFindByContextReturnsFirstNonNull(): void
    {
        $dto = $this->makeDto('mangadex');
        $composite = new CompositeMangaCoverApiClient(
            [
                $this->makeProvider(null),
                $this->makeProvider($dto),
            ],
            new NullLogger(),
        );

        $result = $composite->findByContext('One Piece', null, 1);

        $this->assertSame($dto, $result);
    }

    public function testFindByContextReturnsNullWhenAllFail(): void
    {
        $composite = new CompositeMangaCoverApiClient(
            [$this->makeProvider(null)],
            new NullLogger(),
        );

        $result = $composite->findByContext('Unknown Manga', null, 99);

        $this->assertNull($result);
    }
}
