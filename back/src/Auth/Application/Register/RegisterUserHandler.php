<?php

declare(strict_types=1);

namespace App\Auth\Application\Register;

use App\Auth\Domain\AuthToken;
use App\Auth\Domain\AuthTokenRepositoryInterface;
use App\Auth\Domain\AuthTokenTypeEnum;
use App\Auth\Domain\Exception\EmailAlreadyTakenException;
use App\Auth\Domain\Service\TokenGeneratorInterface;
use App\Auth\Domain\User;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Shared\Event\UserRegisteredEvent;
use App\Shared\Application\Bus\EventBusInterface;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AuthTokenRepositoryInterface $tokenRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private TokenGeneratorInterface $tokenGenerator,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(RegisterUserCommand $command): void
    {
        if ($this->userRepository->findByEmail($command->email) !== null) {
            throw new EmailAlreadyTakenException();
        }

        $user = new User(
            id: Uuid::v4()->toRfc4122(),
            email: strtolower($command->email),
            passwordHash: '',
            displayName: $command->displayName,
        );

        $user->passwordHash = $this->passwordHasher->hashPassword($user, $command->password);

        $this->userRepository->save($user);

        $plainToken  = $this->tokenGenerator->generate();
        $tokenHash   = $this->tokenGenerator->hash($plainToken);
        $authToken   = new AuthToken(
            id: Uuid::v4()->toRfc4122(),
            user: $user,
            type: AuthTokenTypeEnum::EmailVerification,
            tokenHash: $tokenHash,
            expiresAt: new DateTimeImmutable('+7 days'),
        );

        $this->tokenRepository->save($authToken);

        $this->eventBus->publish(new UserRegisteredEvent(
            userId: $user->id,
            email: $user->email,
            displayName: $user->displayName,
            verificationTokenPlain: $plainToken,
        ));
    }
}
