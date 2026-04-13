<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Http;

use App\Notification\Domain\NotificationRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications')]
final readonly class NotificationController
{
    public function __construct(private NotificationRepositoryInterface $repository)
    {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $notifications = array_map(
            static fn ($n) => $n->toArray(),
            $this->repository->findUnread(),
        );

        return new JsonResponse($notifications);
    }

    #[Route('/{id}/read', methods: ['PATCH'])]
    public function markRead(string $id): JsonResponse
    {
        $notification = $this->repository->findById($id);

        if ($notification === null) {
            throw new NotFoundException('Notification', $id);
        }

        $notification->isRead = true;
        $this->repository->save($notification);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
