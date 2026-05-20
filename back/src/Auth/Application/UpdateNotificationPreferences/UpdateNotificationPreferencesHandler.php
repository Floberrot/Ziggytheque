<?php

declare(strict_types=1);

namespace App\Auth\Application\UpdateNotificationPreferences;

use App\Auth\Domain\Exception\UserNotFoundException;
use App\Auth\Domain\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateNotificationPreferencesHandler
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    public function __invoke(UpdateNotificationPreferencesCommand $command): void
    {
        $user = $this->userRepository->findById($command->userId);

        if ($user === null) {
            throw new UserNotFoundException($command->userId);
        }

        $user->updateNotificationPreferences(
            channel: $command->channel,
            notificationEmail: $command->notificationEmail,
            discordWebhookUrl: $command->discordWebhookUrl,
        );

        $this->userRepository->save($user);
    }
}
