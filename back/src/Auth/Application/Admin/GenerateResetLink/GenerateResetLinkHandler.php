<?php

declare(strict_types=1);

namespace App\Auth\Application\Admin\GenerateResetLink;

use App\Auth\Domain\AuthToken;
use App\Auth\Domain\AuthTokenRepositoryInterface;
use App\Auth\Domain\AuthTokenTypeEnum;
use App\Auth\Domain\Exception\UserNotFoundException;
use App\Auth\Domain\Service\TokenGeneratorInterface;
use App\Auth\Domain\UserRepositoryInterface;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class GenerateResetLinkHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AuthTokenRepositoryInterface $tokenRepository,
        private TokenGeneratorInterface $tokenGenerator,
        private string $frontUrl,
    ) {
    }

    public function __invoke(GenerateResetLinkCommand $command): string
    {
        $user = $this->userRepository->findById($command->userId);

        if ($user === null) {
            throw new UserNotFoundException($command->userId);
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

        return rtrim($this->frontUrl, '/') . '/reset-password?token=' . $plainToken;
    }
}
