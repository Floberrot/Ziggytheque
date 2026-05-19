<?php

declare(strict_types=1);

namespace App\Auth\Application\VerifyEmail;

use App\Auth\Domain\AuthTokenRepositoryInterface;
use App\Auth\Domain\AuthTokenTypeEnum;
use App\Auth\Domain\Exception\InvalidTokenException;
use App\Auth\Domain\Service\TokenGeneratorInterface;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Shared\Event\UserEmailVerifiedEvent;
use App\Shared\Application\Bus\EventBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class VerifyEmailHandler
{
    public function __construct(
        private AuthTokenRepositoryInterface $tokenRepository,
        private UserRepositoryInterface $userRepository,
        private TokenGeneratorInterface $tokenGenerator,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(VerifyEmailCommand $command): void
    {
        $tokenHash = $this->tokenGenerator->hash($command->token);
        $authToken = $this->tokenRepository->findValidByHash($tokenHash, AuthTokenTypeEnum::EmailVerification);

        if ($authToken === null) {
            throw new InvalidTokenException();
        }

        $authToken->consume();
        $this->tokenRepository->save($authToken);

        $user = $authToken->user;
        $user->markEmailVerified();
        $this->userRepository->save($user);

        $this->eventBus->publish(new UserEmailVerifiedEvent(
            userId: $user->id,
            email: $user->email,
        ));
    }
}
