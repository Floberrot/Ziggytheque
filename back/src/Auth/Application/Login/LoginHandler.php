<?php

declare(strict_types=1);

namespace App\Auth\Application\Login;

use App\Auth\Domain\Exception\AccountNotActivatedException;
use App\Auth\Domain\Exception\InvalidCredentialsException;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Domain\UserStatusEnum;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class LoginHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function __invoke(LoginCommand $command): string
    {
        $user = $this->userRepository->findByEmail($command->email);

        if ($user === null || !$this->passwordHasher->isPasswordValid($user, $command->password)) {
            throw new InvalidCredentialsException();
        }

        if ($user->status !== UserStatusEnum::Active) {
            throw new AccountNotActivatedException($user->status);
        }

        $user->recordLogin();
        $this->userRepository->save($user);

        return $this->jwtManager->create($user);
    }
}
