<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\Search;

use App\Manga\Application\Search\SearchMangaHandler;
use App\Manga\Application\Search\SearchMangaQuery;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use PHPUnit\Framework\TestCase;

class SearchMangaHandlerTest extends TestCase
{
    public function testReturnsMappedResults(): void
    {
        $manga = new Manga('m-1', 'Naruto', 'Kana', 'fr');
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->method('search')->with('naruto')->willReturn([$manga]);

        $handler = new SearchMangaHandler($repository);
        $result = $handler(new SearchMangaQuery('naruto'));

        $this->assertCount(1, $result);
        $this->assertSame('m-1', $result[0]['id']);
        $this->assertSame('Naruto', $result[0]['title']);
    }

    public function testReturnsEmptyArrayWhenNoResults(): void
    {
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->method('search')->willReturn([]);

        $handler = new SearchMangaHandler($repository);
        $result = $handler(new SearchMangaQuery('xyz'));

        $this->assertSame([], $result);
    }
}
