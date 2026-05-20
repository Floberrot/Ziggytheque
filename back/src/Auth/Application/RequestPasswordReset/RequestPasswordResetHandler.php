<?php

declare(strict_types=1);

namespace App\Auth\Application\RequestPasswordReset;

use App\Auth\Domain\AuthToken;
use App\Auth\Domain\AuthTokenRepositoryInterface;
use App\Auth\Domain\AuthTokenTypeEnum;
use App\Auth\Domain\Service\TokenGeneratorInterface;
use App\Auth\Domain\UserRepositoryInterface;
use App\Auth\Shared\Event\PasswordResetRequestedEvent;
use App\Shared\Application\Bus\EventBusInterface;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RequestPasswordResetHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AuthTokenRepositoryInterface $tokenRepository,
        private TokenGeneratorInterface $tokenGenerator,
        private EventBusInterface $eventBus,
        private string $frontUrl,
    ) {
    }

    public function __invoke(RequestPasswordResetCommand $command): void
    {
        $user = $this->userRepository->findByEmail($command->email);

        if ($user === null) {
            return;
        }

        $plainToken = $this->tokenGenerator->generate();
        $tokenHash  = $this->tokenGenerator->hash($plainToken);
        $authToken  = new AuthToken(
            id: Uuid::v4()->toRfc4122(),
            user: $user,
            type: AuthTokenTypeEnum::PasswordReset,
            tokenHash: $tokenHash,
            expiresAt: new DateTimeImmutable('+24 hours'),
        );

        $this->tokenRepository->save($authToken);

        $resetUrl = rtrim($this->frontUrl, '/') . '/reset-password?token=' . $plainToken;

        $this->eventBus->publish(new PasswordResetRequestedEvent(
            userId: $user->id,
            email: $user->email,
            resetTokenPlain: $plainToken,
            resetUrl: $resetUrl,
        ));
    }
}
