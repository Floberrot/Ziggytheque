<?php

declare(strict_types=1);

namespace App\Auth\Application\Admin\UpdateUser;

use App\Auth\Domain\Exception\UserNotFoundException;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateUserHandler
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    public function __invoke(UpdateUserCommand $command): User
    {
        $user = $this->userRepository->findById($command->userId);

        if ($user === null) {
            throw new UserNotFoundException($command->userId);
        }

        if ($command->displayName !== null) {
            $user->displayName = $command->displayName;
        }

        if ($command->status !== null) {
            $user->status = $command->status;
        }

        if ($command->notificationChannel !== null) {
            // Preserve the user's saved destinations: the admin can change the
            // channel but never sees or edits the actual email/webhook URL.
            $user->updateNotificationPreferences(
                channel: $command->notificationChannel,
                notificationEmail: $user->notificationEmail,
                discordWebhookUrl: $user->discordWebhookUrl,
            );
        }

        $this->userRepository->save($user);

        return $user;
    }
}
