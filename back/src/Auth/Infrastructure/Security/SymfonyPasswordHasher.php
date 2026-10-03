<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Security;

use App\Auth\Domain\Service\PasswordHasherInterface;
use App\Auth\Domain\User;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** The algorithm is the one security.yaml sets for User (password_hashers). */
final readonly class SymfonyPasswordHasher implements PasswordHasherInterface
{
    public function __construct(
        private PasswordHasherFactoryInterface $passwordHasherFactory,
        private UserPasswordHasherInterface $userPasswordHasher,
    ) {
    }

    public function hash(string $plainPassword): string
    {
        return $this->passwordHasherFactory->getPasswordHasher(User::class)->hash($plainPassword);
    }

    public function isValid(User $user, string $plainPassword): bool
    {
        return $this->userPasswordHasher->isPasswordValid($user, $plainPassword);
    }
}
