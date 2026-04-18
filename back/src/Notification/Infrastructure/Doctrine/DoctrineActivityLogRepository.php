<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Doctrine;

use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(ActivityLog $log): void
    {
        $this->em->persist($log);
        $this->em->flush();
    }

    public function findRecent(int $limit = 50): array
    {
        return $this->em->createQueryBuilder()
            ->select('l')
            ->from(ActivityLog::class, 'l')
            ->orderBy('l.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
