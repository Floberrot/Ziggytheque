<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application\GetFollowedEntries;

use App\Collection\Application\Get\GetCollectionQuery;
use App\Collection\Application\GetWishlist\GetWishlistQuery;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Manga\Domain\Manga;
use App\Notification\Application\GetFollowedEntries\GetFollowedEntriesHandler;
use App\Notification\Application\GetFollowedEntries\GetFollowedEntriesQuery;
use PHPUnit\Framework\TestCase;

final class GetFollowedEntriesHandlerTest extends TestCase
{
    /** @param list<CollectionEntry> $followed */
    private function handlerWith(array $followed): GetFollowedEntriesHandler
    {
        $repository = new class ($followed) implements CollectionRepositoryInterface {
            /** @param list<CollectionEntry> $followed */
            public function __construct(private array $followed)
            {
            }

            public function findById(string $id): ?CollectionEntry
            {
                return null;
            }

            public function findByMangaId(string $mangaId): ?CollectionEntry
            {
                return null;
            }

            public function findAll(): array
            {
                return [];
            }

            public function findFiltered(GetCollectionQuery $query): array
            {
                return ['items' => [], 'total' => 0];
            }

            public function findWishedFiltered(GetWishlistQuery $query): array
            {
                return ['items' => [], 'total' => 0];
            }

            public function findFollowed(): array
            {
                return $this->followed;
            }

            public function save(CollectionEntry $entry): void
            {
            }

            public function delete(CollectionEntry $entry): void
            {
            }
        };

        return new GetFollowedEntriesHandler($repository);
    }

    private function entry(string $id, string $title, ?string $coverUrl = null): CollectionEntry
    {
        $manga = new Manga(id: 'm-' . $id, title: $title, edition: null, language: 'fr', coverUrl: $coverUrl);

        return new CollectionEntry(id: $id, manga: $manga);
    }

    public function testReturnsEmptyArrayWhenNothingFollowed(): void
    {
        $result = ($this->handlerWith([]))(new GetFollowedEntriesQuery());

        $this->assertSame([], $result);
    }

    public function testMapsEntryToLightweightShape(): void
    {
        $handler = $this->handlerWith([
            $this->entry('ce-1', 'Naruto', 'https://example.test/naruto.jpg'),
        ]);

        $result = $handler(new GetFollowedEntriesQuery());

        $this->assertSame([
            [
                'id'    => 'ce-1',
                'manga' => [
                    'id'       => 'm-ce-1',
                    'title'    => 'Naruto',
                    'coverUrl' => 'https://example.test/naruto.jpg',
                ],
            ],
        ], $result);
    }

    public function testReturnsAllFollowedEntriesSortedByTitleCaseInsensitively(): void
    {
        $handler = $this->handlerWith([
            $this->entry('ce-z', 'Zatch Bell'),
            $this->entry('ce-a', 'Akira'),
            $this->entry('ce-m', 'monster'),
        ]);

        $result = $handler(new GetFollowedEntriesQuery());

        // Every followed entry is returned (regression: never truncated to one) …
        $this->assertCount(3, $result);

        // … case-insensitively sorted by title.
        $titles = array_column(array_column($result, 'manga'), 'title');
        $this->assertSame(['Akira', 'monster', 'Zatch Bell'], $titles);
    }
}
