<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Http;

use App\Notification\Application\Test\SendTestNotificationMessage;
use App\Shared\Domain\Security\CurrentUserProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/me/notifications')]
#[IsGranted('ROLE_USER')]
final readonly class NotificationTestController
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private CurrentUserProviderInterface $currentUserProvider,
    ) {
    }

    #[Route('/test', methods: ['POST'])]
    public function send(): JsonResponse
    {
        $this->messageBus->dispatch(new SendTestNotificationMessage(
            userId: $this->currentUserProvider->currentUserId(),
        ));

        return new JsonResponse(
            ['message' => 'Test notification dispatched.'],
            Response::HTTP_ACCEPTED,
        );
    }
}
