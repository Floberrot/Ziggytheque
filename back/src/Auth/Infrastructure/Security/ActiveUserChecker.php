<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Security;

use App\Auth\Domain\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Runs on every authenticated API request: a JWT stays valid until it expires,
 * so an account disabled (or not yet approved) after its login is refused here,
 * on its next request, instead of keeping access until the token runs out.
 */
final readonly class ActiveUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User || $user->isActive()) {
            return;
        }

        throw new CustomUserMessageAccountStatusException('Account is not active.');
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
