<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http;

use App\Auth\Application\UpdateNotificationPreferences\UpdateNotificationPreferencesCommand;
use App\Auth\Domain\User;
use App\Auth\Infrastructure\Http\Request\UpdateNotificationPreferencesRequest;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Security\CurrentUserProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/me')]
#[IsGranted('ROLE_USER')]
final readonly class ProfileController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private CurrentUserProviderInterface $currentUserProvider,
        private Security $security,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();

        return new JsonResponse($user->toArray());
    }

    #[Route('/notifications', methods: ['PATCH'])]
    public function updateNotifications(
        #[MapRequestPayload] UpdateNotificationPreferencesRequest $request,
    ): JsonResponse {
        $this->commandBus->dispatch(new UpdateNotificationPreferencesCommand(
            userId: $this->currentUserProvider->currentUserId(),
            channel: $request->channel,
            notificationEmail: $request->notificationEmail,
            discordWebhookUrl: $request->discordWebhookUrl,
        ));

        return new JsonResponse(['message' => 'Notification preferences updated.']);
    }
}
