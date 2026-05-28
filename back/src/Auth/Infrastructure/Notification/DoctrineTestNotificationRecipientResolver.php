<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Notification;

use App\Auth\Domain\UserRepositoryInterface;
use App\Notification\Domain\TestNotificationRecipient;
use App\Notification\Domain\TestNotificationRecipientResolverInterface;
use App\Shared\Domain\Exception\NotFoundException;

final readonly class DoctrineTestNotificationRecipientResolver implements TestNotificationRecipientResolverInterface
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    public function resolve(string $userId): TestNotificationRecipient
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new NotFoundException('User', $userId);
        }

        return new TestNotificationRecipient(
            user: $user,
            displayName: $user->displayName,
            channel: $user->notificationChannel->value,
            notificationEmail: $user->notificationEmail,
            discordWebhookUrl: $user->discordWebhookUrl,
        );
    }
}
