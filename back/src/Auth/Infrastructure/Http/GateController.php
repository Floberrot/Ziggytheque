<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http;

use App\Auth\Application\Gate\GateCommand;
use App\Auth\Domain\User;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Infrastructure\RateLimit\CacheRateLimiter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/auth')]
final readonly class GateController
{
    /** The gate password is a single shared secret — cap guesses per account. */
    private const int GATE_LIMIT  = 10;
    private const int GATE_WINDOW = 900;

    public function __construct(
        private CommandBusInterface $commandBus,
        private Security $security,
        private CacheRateLimiter $rateLimiter,
    ) {
    }

    #[Route('/gate', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function gate(#[MapRequestPayload] GateRequest $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        $this->rateLimiter->consume(
            'auth_gate:' . $currentUser->id,
            self::GATE_LIMIT,
            self::GATE_WINDOW,
        );

        $token = $this->commandBus->dispatch(new GateCommand(
            password: $request->password,
            user: $currentUser,
        ));

        return new JsonResponse(['token' => $token]);
    }
}
