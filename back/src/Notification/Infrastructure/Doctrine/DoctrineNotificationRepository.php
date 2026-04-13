<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Doctrine;

use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineNotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findUnread(): array
    {
        return $this->em->getRepository(Notification::class)
            ->findBy(['isRead' => false], ['createdAt' => 'DESC']);
    }

    public function findById(string $id): ?Notification
    {
        return $this->em->find(Notification::class, $id);
    }

    public function save(Notification $notification): void
    {
        $this->em->persist($notification);
        $this->em->flush();
    }
}
