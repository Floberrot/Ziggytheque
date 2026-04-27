<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain;

use App\Notification\Domain\EventTypeEnum;
use PHPUnit\Framework\TestCase;

final class EventTypeEnumTest extends TestCase
{
    public function testValues(): void
    {
        $this->assertSame('rss_fetch', EventTypeEnum::RssFetch->value);
        $this->assertSame('jikan_fetch', EventTypeEnum::JikanFetch->value);
        $this->assertSame('discord_sent', EventTypeEnum::DiscordSent->value);
        $this->assertSame('scheduler_fire', EventTypeEnum::SchedulerFire->value);
        $this->assertSame('http_error', EventTypeEnum::HttpError->value);
        $this->assertSame('worker_failure', EventTypeEnum::WorkerFailure->value);
        $this->assertSame('user_action', EventTypeEnum::UserAction->value);
        $this->assertSame('collection_action', EventTypeEnum::CollectionAction->value);
        $this->assertSame('manga_action', EventTypeEnum::MangaAction->value);
        $this->assertSame('auth_action', EventTypeEnum::AuthAction->value);
        $this->assertSame('wishlist_action', EventTypeEnum::WishlistAction->value);
    }
}
