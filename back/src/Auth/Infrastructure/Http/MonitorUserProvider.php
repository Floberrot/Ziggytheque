<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http;

use App\Shared\Domain\Security\SecretStrength;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The single HTTP Basic user of /messenger. With an empty, placeholder or short
 * MONITOR_PASSWORD nobody can sign in: the dashboard stays closed.
 *
 * @implements UserProviderInterface<MonitorUser>
 */
final readonly class MonitorUserProvider implements UserProviderInterface
{
    public function __construct(
        private string $monitorUser,
        private string $monitorPassword,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if (SecretStrength::isWeak($this->monitorPassword, SecretStrength::MIN_PASSWORD_LENGTH)) {
            throw new UserNotFoundException('The messenger monitor is disabled until MONITOR_PASSWORD is strong.');
        }

        $user     = $this->monitorUser;
        $password = $this->monitorPassword;
        if ($user !== '' && $password !== '' && hash_equals($user, $identifier)) {
            return new MonitorUser($user, $password);
        }

        throw new UserNotFoundException(sprintf('Monitor user "%s" not found.', $identifier));
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof MonitorUser) {
            throw new UnsupportedUserException();
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === MonitorUser::class;
    }
}
