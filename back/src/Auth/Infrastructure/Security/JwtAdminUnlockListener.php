<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_authenticated')]
final readonly class JwtAdminUnlockListener
{
    public function __invoke(JWTAuthenticatedEvent $event): void
    {
        if (!($event->getPayload()['adminUnlocked'] ?? false)) {
            return;
        }

        $token = $event->getToken();
        $token->setRoleNames(array_unique([...$token->getRoleNames(), 'ROLE_ADMIN_UNLOCKED']));
    }
}
