<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Domain;

use App\Manga\Domain\Manga;
use App\Wishlist\Domain\WishlistItem;
use PHPUnit\Framework\TestCase;

class WishlistItemTest extends TestCase
{
    private Manga $manga;

    protected function setUp(): void
    {
        $this->manga = new Manga('m-1', 'Test Manga', 'Ed', 'fr');
    }

    public function testDefaultsNotPurchased(): void
    {
        $item = new WishlistItem('wi-1', $this->manga);

        $this->assertSame('wi-1', $item->id);
        $this->assertSame($this->manga, $item->manga);
        $this->assertFalse($item->isPurchased);
        $this->assertInstanceOf(\DateTimeImmutable::class, $item->addedAt);
    }

    public function testToArrayContainsExpectedKeys(): void
    {
        $item = new WishlistItem('wi-1', $this->manga);
        $arr = $item->toArray();

        $this->assertArrayHasKey('id', $arr);
        $this->assertArrayHasKey('manga', $arr);
        $this->assertArrayHasKey('isPurchased', $arr);
        $this->assertArrayHasKey('addedAt', $arr);
        $this->assertSame('wi-1', $arr['id']);
        $this->assertFalse($arr['isPurchased']);
    }
}
