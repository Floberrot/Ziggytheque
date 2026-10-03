<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain\Service;

use App\Notification\Domain\Service\RssFeedItem;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RssFeedItemTest extends TestCase
{
    public function testHoldsWhatTheFeedSaysOfTheItem(): void
    {
        $publishedAt = new DateTimeImmutable('2026-05-01 10:00:00');

        $feedItem = new RssFeedItem(
            title: 'One Piece : le tome 110',
            description: 'Glénat annonce la date.',
            url: 'https://example.com/one-piece-110',
            publishedAt: $publishedAt,
            imageUrl: 'https://example.com/cover.jpg',
        );

        $this->assertSame('One Piece : le tome 110', $feedItem->title);
        $this->assertSame('Glénat annonce la date.', $feedItem->description);
        $this->assertSame('https://example.com/one-piece-110', $feedItem->url);
        $this->assertSame($publishedAt, $feedItem->publishedAt);
        $this->assertSame('https://example.com/cover.jpg', $feedItem->imageUrl);
    }

    public function testDateAndImageAreOptional(): void
    {
        $feedItem = new RssFeedItem('Title', '', 'https://example.com', null, null);

        $this->assertNull($feedItem->publishedAt);
        $this->assertNull($feedItem->imageUrl);
    }
}
