<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Doctrine;

use App\Auth\Shared\Dto\UserDto;
use App\Auth\Shared\UserReaderInterface;

final readonly class DoctrineUserReader implements UserReaderInterface
{
    public function __construct(private DoctrineUserRepository $repository)
    {
    }

    public function findById(string $id): ?UserDto
    {
        $user = $this->repository->findById($id);

        if ($user === null) {
            return null;
        }

        return new UserDto(
            id: $user->id,
            email: $user->email,
            displayName: $user->displayName,
            role: $user->role->value,
            status: $user->status->value,
            notificationChannel: $user->notificationChannel->value,
            notificationEmail: $user->notificationEmail,
            discordWebhookUrl: $user->discordWebhookUrl,
        );
    }
}
