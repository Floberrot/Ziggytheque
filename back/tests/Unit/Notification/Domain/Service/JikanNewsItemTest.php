<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain\Service;

use App\Notification\Domain\Service\JikanNewsItem;
use PHPUnit\Framework\TestCase;

final class JikanNewsItemTest extends TestCase
{
    public function testHoldsWhatJikanSaysOfTheNews(): void
    {
        $newsItem = new JikanNewsItem(
            url: 'https://myanimelist.net/news/1',
            title: 'One Piece anime news',
            excerpt: 'The anime returns.',
            author: 'mal-editor',
            date: '2026-05-01T10:00:00+00:00',
        );

        $this->assertSame('https://myanimelist.net/news/1', $newsItem->url);
        $this->assertSame('One Piece anime news', $newsItem->title);
        $this->assertSame('The anime returns.', $newsItem->excerpt);
        $this->assertSame('mal-editor', $newsItem->author);
        $this->assertSame('2026-05-01T10:00:00+00:00', $newsItem->date);
    }

    public function testLinkAuthorAndDateAreOptional(): void
    {
        $newsItem = new JikanNewsItem(null, '', '', null, null);

        $this->assertNull($newsItem->url);
        $this->assertNull($newsItem->author);
        $this->assertNull($newsItem->date);
    }
}
