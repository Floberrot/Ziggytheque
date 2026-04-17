<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http;

use App\Auth\Domain\GateUser;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<GateUser|MonitorUser> */
final readonly class GateUserProvider implements UserProviderInterface
{
    /**
     * @phpstan-param non-empty-string $monitorUser
     * @phpstan-param non-empty-string $monitorPassword
     */
    public function __construct(
        private string $monitorUser,
        private string $monitorPassword,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if ($identifier === $this->monitorUser) {
            return new MonitorUser($this->monitorUser, $this->monitorPassword);
        }

        if ($identifier === 'gate') {
            return new GateUser();
        }

        throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof GateUser && !$user instanceof MonitorUser) {
            throw new UnsupportedUserException();
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === GateUser::class || $class === MonitorUser::class;
    }
}
