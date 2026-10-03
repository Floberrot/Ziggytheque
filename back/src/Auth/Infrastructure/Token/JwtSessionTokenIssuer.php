<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Token;

use App\Auth\Domain\Service\SessionTokenIssuerInterface;
use App\Auth\Domain\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/** Session tokens are Lexik JWTs; their lifetime is JWT_TTL (lexik_jwt_authentication.yaml). */
final readonly class JwtSessionTokenIssuer implements SessionTokenIssuerInterface
{
    /** Claim DoctrineUserProvider reads back to grant ROLE_ADMIN_UNLOCKED. */
    public const string ADMIN_UNLOCKED_CLAIM = 'adminUnlocked';

    public function __construct(private JWTTokenManagerInterface $jwtManager)
    {
    }

    public function issue(User $user): string
    {
        return $this->jwtManager->create($user);
    }

    public function issueAdminUnlocked(User $user): string
    {
        return $this->jwtManager->createFromPayload($user, [self::ADMIN_UNLOCKED_CLAIM => true]);
    }
}
