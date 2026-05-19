<?php

declare(strict_types=1);

namespace App\Auth\Application\ResetPassword;

use App\Auth\Domain\AuthTokenRepositoryInterface;
use App\Auth\Domain\AuthTokenTypeEnum;
use App\Auth\Domain\Exception\InvalidTokenException;
use App\Auth\Domain\Service\TokenGeneratorInterface;
use App\Auth\Domain\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ResetPasswordHandler
{
    public function __construct(
        private AuthTokenRepositoryInterface $tokenRepository,
        private UserRepositoryInterface $userRepository,
        private TokenGeneratorInterface $tokenGenerator,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(ResetPasswordCommand $command): void
    {
        $tokenHash = $this->tokenGenerator->hash($command->token);
        $authToken = $this->tokenRepository->findValidByHash($tokenHash, AuthTokenTypeEnum::PasswordReset);

        if ($authToken === null) {
            throw new InvalidTokenException();
        }

        $authToken->consume();
        $this->tokenRepository->save($authToken);

        $user = $authToken->user;
        $user->changePassword($this->passwordHasher->hashPassword($user, $command->newPassword));
        $this->userRepository->save($user);
    }
}
