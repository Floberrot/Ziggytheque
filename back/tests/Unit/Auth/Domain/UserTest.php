<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Domain;

use App\Auth\Domain\NotificationChannelEnum;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRoleEnum;
use App\Auth\Domain\UserStatusEnum;
use App\Shared\Domain\Exception\InvalidDiscordWebhookUrlException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    private function makeUser(
        UserRoleEnum $role = UserRoleEnum::User,
        UserStatusEnum $status = UserStatusEnum::PendingEmailVerification,
    ): User {
        return new User(
            id: 'test-uuid-1234',
            email: 'user@example.com',
            passwordHash: 'hashed',
            displayName: 'Test User',
            role: $role,
            status: $status,
        );
    }

    public function testCreateAdminBuildsActiveAdmin(): void
    {
        $user = User::createAdmin('id-1', 'Admin@Example.COM', 'hash', 'Admin');

        $this->assertSame('admin@example.com', $user->email);
        $this->assertSame(UserRoleEnum::Admin, $user->role);
        $this->assertSame(UserStatusEnum::Active, $user->status);
    }

    public function testGetRolesForRegularUser(): void
    {
        $user = $this->makeUser(UserRoleEnum::User);
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testGetRolesForAdmin(): void
    {
        $user = $this->makeUser(UserRoleEnum::Admin);
        $this->assertContains('ROLE_USER', $user->getRoles());
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testMarkEmailVerifiedTransitionsToPendingApproval(): void
    {
        $user = $this->makeUser(status: UserStatusEnum::PendingEmailVerification);
        $user->markEmailVerified();

        $this->assertSame(UserStatusEnum::PendingAdminApproval, $user->status);
    }

    public function testMarkEmailVerifiedIsIdempotentWhenAlreadyApproved(): void
    {
        $user = $this->makeUser(status: UserStatusEnum::Active);
        $user->markEmailVerified();

        $this->assertSame(UserStatusEnum::Active, $user->status);
    }

    public function testApproveTransitionsToActive(): void
    {
        $user = $this->makeUser(status: UserStatusEnum::PendingAdminApproval);
        $user->approve();

        $this->assertSame(UserStatusEnum::Active, $user->status);
    }

    public function testDisableTransitionsToDisabled(): void
    {
        $user = $this->makeUser(status: UserStatusEnum::Active);
        $user->disable();

        $this->assertSame(UserStatusEnum::Disabled, $user->status);
    }

    public function testChangePassword(): void
    {
        $user = $this->makeUser();
        $user->changePassword('new-hash');

        $this->assertSame('new-hash', $user->passwordHash);
        $this->assertSame('new-hash', $user->getPassword());
    }

    public function testUpdateNotificationPreferences(): void
    {
        $user = $this->makeUser();
        $user->updateNotificationPreferences(
            channel: NotificationChannelEnum::Discord,
            notificationEmail: null,
            discordWebhookUrl: 'https://discord.com/api/webhooks/123/token',
        );

        $this->assertSame(NotificationChannelEnum::Discord, $user->notificationChannel);
        $this->assertNull($user->notificationEmail);
        $this->assertSame('https://discord.com/api/webhooks/123/token', $user->discordWebhookUrl);
    }

    /**
     * The webhook URL is called server-side, so the invariant lives on the
     * entity: no caller can persist a URL pointing somewhere else.
     */
    #[DataProvider('nonDiscordWebhookUrls')]
    public function testUpdateNotificationPreferencesRejectsNonDiscordWebhook(string $webhookUrl): void
    {
        $user = $this->makeUser();

        $this->expectException(InvalidDiscordWebhookUrlException::class);

        $user->updateNotificationPreferences(
            channel: NotificationChannelEnum::Discord,
            notificationEmail: null,
            discordWebhookUrl: $webhookUrl,
        );
    }

    /** @return iterable<string, array{string}> */
    public static function nonDiscordWebhookUrls(): iterable
    {
        yield 'cloud metadata'     => ['http://169.254.169.254/latest/meta-data/'];
        yield 'internal service'   => ['http://back:80/api/me'];
        yield 'lookalike host'     => ['https://discord.com.attacker.example/api/webhooks/1/t'];
        yield 'userinfo smuggling' => ['https://discord.com@attacker.example/api/webhooks/1/t'];
        yield 'wrong path'         => ['https://discord.com/webhook/xxx'];
    }

    /** An absent webhook is normalised to null, so "not set" has one representation. */
    #[DataProvider('emptyWebhookUrls')]
    public function testUpdateNotificationPreferencesAcceptsNoWebhook(?string $webhookUrl): void
    {
        $user = $this->makeUser();
        $user->updateNotificationPreferences(
            channel: NotificationChannelEnum::Email,
            notificationEmail: 'user@example.com',
            discordWebhookUrl: $webhookUrl,
        );

        $this->assertNull($user->discordWebhookUrl);
    }

    /** @return iterable<string, array{string|null}> */
    public static function emptyWebhookUrls(): iterable
    {
        yield 'null'         => [null];
        yield 'empty string' => [''];
    }

    public function testRecordLoginSetsLastLoginAt(): void
    {
        $user = $this->makeUser();
        $this->assertNull($user->lastLoginAt);

        $user->recordLogin();

        $this->assertNotNull($user->lastLoginAt);
    }

    public function testGetUserIdentifier(): void
    {
        $user = $this->makeUser();
        $this->assertSame('user@example.com', $user->getUserIdentifier());
    }

    public function testToArrayShape(): void
    {
        $user  = $this->makeUser();
        $array = $user->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('displayName', $array);
        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('notificationChannel', $array);
    }

    public function testToAdminArrayMasksEmailAndDiscordWebhook(): void
    {
        $user = new User(
            id: 'id-1',
            email: 'user@example.com',
            passwordHash: 'hash',
            displayName: 'Alice',
            notificationChannel: NotificationChannelEnum::Email,
            notificationEmail: 'private@example.com',
            discordWebhookUrl: 'https://discord.com/api/webhooks/x/y',
        );

        $array = $user->toAdminArray();

        $this->assertArrayNotHasKey('notificationEmail', $array);
        $this->assertArrayNotHasKey('discordWebhookUrl', $array);
        $this->assertSame('email', $array['notificationChannel']);
        $this->assertTrue($array['notificationConfigured']);
    }

    public function testToAdminArrayReportsUnconfiguredWhenChannelHasNoDestination(): void
    {
        $user = new User(
            id: 'id-1',
            email: 'user@example.com',
            passwordHash: 'hash',
            displayName: 'Alice',
            notificationChannel: NotificationChannelEnum::Discord,
            notificationEmail: 'private@example.com',
            discordWebhookUrl: null,
        );

        $this->assertFalse($user->toAdminArray()['notificationConfigured']);
    }
}
