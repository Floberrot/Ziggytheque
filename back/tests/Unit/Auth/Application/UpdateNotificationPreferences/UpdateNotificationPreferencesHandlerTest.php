<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Application\UpdateNotificationPreferences;

use App\Auth\Application\UpdateNotificationPreferences\UpdateNotificationPreferencesCommand;
use App\Auth\Application\UpdateNotificationPreferences\UpdateNotificationPreferencesHandler;
use App\Auth\Domain\Exception\UserNotFoundException;
use App\Auth\Domain\NotificationChannelEnum;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Shared\Domain\Exception\InvalidDiscordWebhookUrlException;
use PHPUnit\Framework\TestCase;

final class UpdateNotificationPreferencesHandlerTest extends TestCase
{
    private function account(): User
    {
        return new User(id: 'user-1', email: 'reader@example.com', passwordHash: 'hash', displayName: 'Reader');
    }

    public function testSavesTheChannelAndItsDestinations(): void
    {
        $account        = $this->account();
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())->method('findById')->with('user-1')->willReturn($account);
        $userRepository->expects($this->once())->method('save')->with($account);

        (new UpdateNotificationPreferencesHandler($userRepository))(new UpdateNotificationPreferencesCommand(
            userId: 'user-1',
            channel: NotificationChannelEnum::Discord,
            notificationEmail: 'alerts@example.com',
            discordWebhookUrl: 'https://discord.com/api/webhooks/1/abc',
        ));

        $this->assertSame(NotificationChannelEnum::Discord, $account->notificationChannel);
        $this->assertSame('alerts@example.com', $account->notificationEmail);
        $this->assertSame('https://discord.com/api/webhooks/1/abc', $account->discordWebhookUrl);
    }

    /** The server posts to the webhook itself: anything outside discord.com is refused. */
    public function testAWebhookOutsideDiscordIsRefusedAndNothingSaved(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())->method('findById')->willReturn($this->account());
        $userRepository->expects($this->never())->method('save');

        $this->expectException(InvalidDiscordWebhookUrlException::class);

        (new UpdateNotificationPreferencesHandler($userRepository))(new UpdateNotificationPreferencesCommand(
            userId: 'user-1',
            channel: NotificationChannelEnum::Discord,
            notificationEmail: null,
            discordWebhookUrl: 'http://169.254.169.254/latest/meta-data/',
        ));
    }

    public function testAnUnknownAccountIsNotFound(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects($this->once())->method('findById')->willReturn(null);
        $userRepository->expects($this->never())->method('save');

        $this->expectException(UserNotFoundException::class);

        (new UpdateNotificationPreferencesHandler($userRepository))(new UpdateNotificationPreferencesCommand(
            userId: 'ghost',
            channel: NotificationChannelEnum::Email,
            notificationEmail: 'alerts@example.com',
            discordWebhookUrl: null,
        ));
    }
}
