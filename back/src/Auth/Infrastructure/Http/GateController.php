<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http;

use App\Auth\Application\Gate\GateCommand;
use App\Auth\Domain\User;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/auth')]
final readonly class GateController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private Security $security,
    ) {
    }

    #[Route('/gate', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function gate(#[MapRequestPayload] GateRequest $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        $token = $this->commandBus->dispatch(new GateCommand(
            password: $request->password,
            user: $currentUser,
        ));

        return new JsonResponse(['token' => $token]);
    }
}
