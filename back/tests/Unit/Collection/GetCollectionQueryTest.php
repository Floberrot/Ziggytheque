<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection;

use App\Collection\Application\Get\GetCollectionQuery;
use App\Collection\Domain\CollectionSortEnum;
use App\Collection\Domain\ReadingStatusEnum;
use App\Manga\Domain\GenreEnum;
use PHPUnit\Framework\TestCase;

final class GetCollectionQueryTest extends TestCase
{
    public function testDefaultsAreCorrect(): void
    {
        $query = new GetCollectionQuery();

        $this->assertNull($query->search);
        $this->assertNull($query->genre);
        $this->assertNull($query->edition);
        $this->assertNull($query->readingStatus);
        $this->assertNull($query->sort);
        $this->assertFalse($query->followedOnly);
        $this->assertSame(1, $query->page);
        $this->assertSame(20, $query->limit);
    }

    public function testAllArgsStoredCorrectly(): void
    {
        $query = new GetCollectionQuery(
            search:        'naruto',
            genre:         GenreEnum::Shonen,
            edition:       'Deluxe',
            readingStatus: ReadingStatusEnum::InProgress,
            sort:          CollectionSortEnum::RatingDesc,
            followedOnly:  true,
            page:          3,
            limit:         10,
        );

        $this->assertSame('naruto', $query->search);
        $this->assertSame(GenreEnum::Shonen, $query->genre);
        $this->assertSame('Deluxe', $query->edition);
        $this->assertSame(ReadingStatusEnum::InProgress, $query->readingStatus);
        $this->assertSame(CollectionSortEnum::RatingDesc, $query->sort);
        $this->assertTrue($query->followedOnly);
        $this->assertSame(3, $query->page);
        $this->assertSame(10, $query->limit);
    }
}
