<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Domain;

use App\Auth\Domain\NotificationChannelEnum;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRoleEnum;
use App\Auth\Domain\UserStatusEnum;
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
            discordWebhookUrl: 'https://discord.com/webhook/xxx',
        );

        $this->assertSame(NotificationChannelEnum::Discord, $user->notificationChannel);
        $this->assertNull($user->notificationEmail);
        $this->assertSame('https://discord.com/webhook/xxx', $user->discordWebhookUrl);
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
}
