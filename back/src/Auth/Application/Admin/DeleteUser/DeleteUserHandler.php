<?php

declare(strict_types=1);

namespace App\Auth\Application\Admin\DeleteUser;

use App\Auth\Domain\Exception\UserNotFoundException;
use App\Auth\Domain\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteUserHandler
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    public function __invoke(DeleteUserCommand $command): void
    {
        $user = $this->userRepository->findById($command->userId);

        if ($user === null) {
            throw new UserNotFoundException($command->userId);
        }

        $this->userRepository->delete($user);
    }
}
