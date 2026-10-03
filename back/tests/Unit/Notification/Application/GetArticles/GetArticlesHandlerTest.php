<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application\GetArticles;

use App\Collection\Domain\CollectionEntry;
use App\Manga\Domain\Manga;
use App\Notification\Application\GetArticles\GetArticlesHandler;
use App\Notification\Application\GetArticles\GetArticlesQuery;
use App\Notification\Domain\Article;
use App\Notification\Domain\ArticleRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GetArticlesHandlerTest extends TestCase
{
    public function testReturnsThePageOfArticlesWithItsPageCount(): void
    {
        $entry   = new CollectionEntry(id: 'entry-1', manga: new Manga(id: 'manga-1', title: 'Berserk', edition: null, language: 'fr'));
        $article = new Article(
            id: 'article-1',
            collectionEntry: $entry,
            title: 'Berserk : un nouveau tome',
            url: 'https://news.example/berserk',
            sourceName: 'manga-news',
            author: null,
            imageUrl: null,
            publishedAt: new DateTimeImmutable('2026-09-01'),
        );
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findPaginated')
            ->with(2, 12, 'entry-1')
            ->willReturn(['items' => [$article], 'total' => 25]);

        $page = (new GetArticlesHandler($repository))(new GetArticlesQuery(page: 2, limit: 12, collectionEntryId: 'entry-1'));

        $this->assertSame([$article->toArray()], $page['items']);
        $this->assertSame(25, $page['total']);
        $this->assertSame(2, $page['page']);
        $this->assertSame(12, $page['limit']);
        $this->assertSame(3, $page['totalPages']);
    }

    public function testNoArticleMeansNoPage(): void
    {
        $repository = $this->createStub(ArticleRepositoryInterface::class);
        $repository->method('findPaginated')->willReturn(['items' => [], 'total' => 0]);

        $page = (new GetArticlesHandler($repository))(new GetArticlesQuery());

        $this->assertSame([], $page['items']);
        $this->assertSame(0, $page['totalPages']);
    }
}
