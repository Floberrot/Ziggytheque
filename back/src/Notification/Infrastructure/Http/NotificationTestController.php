<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Http;

use App\Notification\Application\Test\SendTestNotificationMessage;
use App\Shared\Domain\Security\CurrentUserProviderInterface;
use App\Shared\Infrastructure\RateLimit\CacheRateLimiter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/me/notifications')]
#[IsGranted('ROLE_USER')]
final readonly class NotificationTestController
{
    /** Each test sends a real email or Discord message. */
    private const int TEST_LIMIT  = 5;
    private const int TEST_WINDOW = 900;

    public function __construct(
        private MessageBusInterface $messageBus,
        private CurrentUserProviderInterface $currentUserProvider,
        private CacheRateLimiter $rateLimiter,
    ) {
    }

    #[Route('/test', methods: ['POST'])]
    public function send(): JsonResponse
    {
        $userId = $this->currentUserProvider->currentUserId();
        $this->rateLimiter->consume('notification-test:' . $userId, self::TEST_LIMIT, self::TEST_WINDOW);

        $this->messageBus->dispatch(new SendTestNotificationMessage(userId: $userId));

        return new JsonResponse(
            ['message' => 'Test notification dispatched.'],
            Response::HTTP_ACCEPTED,
        );
    }
}
