<?php

declare(strict_types=1);

namespace App\Auth\Application\Admin\ApproveUser;

use App\Auth\Domain\Exception\UserNotFoundException;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Shared\Event\UserApprovedEvent;
use App\Shared\Application\Bus\EventBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ApproveUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(ApproveUserCommand $command): void
    {
        $user = $this->userRepository->findById($command->userId);

        if ($user === null) {
            throw new UserNotFoundException($command->userId);
        }

        $user->approve();
        $this->userRepository->save($user);

        $this->eventBus->publish(new UserApprovedEvent(
            userId: $user->id,
            email: $user->email,
            displayName: $user->displayName,
        ));
    }
}
