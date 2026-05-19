<?php

declare(strict_types=1);

namespace App\Auth\Application\Admin\GetUser;

use App\Auth\Domain\Exception\UserNotFoundException;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetUserHandler
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    public function __invoke(GetUserQuery $query): User
    {
        $user = $this->userRepository->findById($query->userId);

        if ($user === null) {
            throw new UserNotFoundException($query->userId);
        }

        return $user;
    }
}
