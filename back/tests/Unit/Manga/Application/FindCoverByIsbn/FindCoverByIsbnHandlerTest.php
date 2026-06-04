<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\FindCoverByIsbn;

use App\Manga\Application\FindCoverByIsbn\FindCoverByIsbnHandler;
use App\Manga\Application\FindCoverByIsbn\FindCoverByIsbnQuery;
use App\Manga\Domain\Exception\InvalidIsbnException;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Domain\MultiSourceCoverProviderInterface;
use PHPUnit\Framework\TestCase;

final class FindCoverByIsbnHandlerTest extends TestCase
{
    /** @param list<MangaVolumeCoverDto> $covers */
    private function makeProvider(array $covers): MultiSourceCoverProviderInterface
    {
        return new class ($covers) implements MultiSourceCoverProviderInterface {
            /** @param list<MangaVolumeCoverDto> $covers */
            public function __construct(private readonly array $covers)
            {
            }

            public function findAllByIsbn(Isbn $isbn): array
            {
                return $this->covers;
            }

            public function findAllByContext(string $mangaTitle, ?string $edition, int $volumeNumber, string $language = 'fr'): array
            {
                return [];
            }
        };
    }

    public function testReturnsEmptyArrayWhenNoCoverFound(): void
    {
        $handler = new FindCoverByIsbnHandler($this->makeProvider([]));

        $result = $handler(new FindCoverByIsbnQuery('9782811645632'));

        $this->assertSame([], $result);
    }

    public function testReturnsEveryCoverGroupedAndMapped(): void
    {
        $isbn = Isbn::fromString('9782811645632');
        $provider = $this->makeProvider([
            new MangaVolumeCoverDto(
                coverUrl: 'https://bnf.example/c.jpg',
                spineUrl: null,
                isbn: $isbn,
                source: 'bnf',
            ),
            new MangaVolumeCoverDto(
                coverUrl: 'https://google.example/c.jpg',
                spineUrl: 'https://google.example/s.jpg',
                isbn: $isbn,
                source: 'google_books',
            ),
        ]);

        $result = (new FindCoverByIsbnHandler($provider))(new FindCoverByIsbnQuery('9782811645632'));

        $this->assertSame([
            [
                'coverUrl' => 'https://bnf.example/c.jpg',
                'spineUrl' => null,
                'isbn' => '9782811645632',
                'source' => 'bnf',
            ],
            [
                'coverUrl' => 'https://google.example/c.jpg',
                'spineUrl' => 'https://google.example/s.jpg',
                'isbn' => '9782811645632',
                'source' => 'google_books',
            ],
        ], $result);
    }

    public function testThrowsInvalidIsbnExceptionForInvalidInput(): void
    {
        $handler = new FindCoverByIsbnHandler($this->makeProvider([]));

        $this->expectException(InvalidIsbnException::class);
        $handler(new FindCoverByIsbnQuery('abc'));
    }
}
