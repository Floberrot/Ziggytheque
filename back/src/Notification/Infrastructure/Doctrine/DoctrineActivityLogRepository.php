<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Doctrine;

use App\Collection\Domain\CollectionEntry;
use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

final readonly class DoctrineActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function __construct(private ManagerRegistry $doctrine)
    {
    }

    private function em(): EntityManagerInterface
    {
        $em = $this->doctrine->getManager();
        assert($em instanceof EntityManagerInterface);

        if (!$em->isOpen()) {
            $this->doctrine->resetManager();
            $em = $this->doctrine->getManager();
            assert($em instanceof EntityManagerInterface);
        }

        return $em;
    }

    public function save(ActivityLog $log): void
    {
        $em = $this->em();

        // Re-resolve collectionEntry in the current EM. After a CollectionEntry is deleted,
        // the in-memory reference on the log is a detached/removed object that causes
        // "new entity found" on the next flush. find() returns null when the row is gone,
        // which is correct: ON DELETE SET NULL already nulled the FK in the DB.
        if ($log->collectionEntry !== null) {
            $log->collectionEntry = $em->find(CollectionEntry::class, $log->collectionEntry->id);
        }

        $em->persist($log);
        $em->flush();
    }

    public function findById(string $id): ?ActivityLog
    {
        return $this->em()->find(ActivityLog::class, $id);
    }

    public function findPaginated(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->em()->createQueryBuilder()
            ->select('l')
            ->from(ActivityLog::class, 'l')
            ->leftJoin('l.collectionEntry', 'ce')
            ->orderBy('l.startedAt', 'DESC');

        if (isset($filters['eventType'])) {
            $qb->andWhere('l.eventType = :et')
               ->setParameter('et', $filters['eventType']);
        }

        if (isset($filters['status'])) {
            $qb->andWhere('l.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (isset($filters['collectionEntryId'])) {
            $qb->andWhere('ce.id = :ceId')
               ->setParameter('ceId', $filters['collectionEntryId']);
        }

        $total = (clone $qb)->select('COUNT(l.id)')->resetDQLPart('orderBy')
            ->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => (int) $total];
    }

    public function countRecentErrors(int $windowMinutes = 10): int
    {
        $since = new DateTimeImmutable("-{$windowMinutes} minutes");

        return (int) $this->em()->createQueryBuilder()
            ->select('COUNT(l.id)')
            ->from(ActivityLog::class, 'l')
            ->where('l.status = :status')
            ->andWhere('l.startedAt >= :since')
            ->setParameter('status', 'error')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
