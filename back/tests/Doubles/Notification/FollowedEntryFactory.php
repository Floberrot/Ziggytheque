<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Notification;

use App\Auth\Domain\NotificationChannelEnum;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRoleEnum;
use App\Auth\Domain\UserStatusEnum;
use App\Collection\Domain\CollectionEntry;
use App\Manga\Domain\Manga;

/** Followed collection entries (each on its owner's own copy of the series) for unit tests. */
final class FollowedEntryFactory
{
    public static function owner(string $id): User
    {
        return new User(
            id: $id,
            email: $id . '@test.local',
            passwordHash: 'hash',
            displayName: $id,
            role: UserRoleEnum::User,
            status: UserStatusEnum::Active,
            notificationChannel: NotificationChannelEnum::Email,
        );
    }

    public static function entry(
        string $entryId,
        string $mangaTitle,
        ?string $malId = null,
        ?User $owner = null,
        ?string $coverUrl = null,
    ): CollectionEntry {
        $manga = new Manga(
            id: 'manga-' . $entryId,
            title: $mangaTitle,
            edition: null,
            language: 'fr',
            coverUrl: $coverUrl,
            externalId: $malId,
            owner: $owner,
        );

        return new CollectionEntry(
            id: $entryId,
            manga: $manga,
            owner: $owner,
            notificationsEnabled: true,
        );
    }
}
