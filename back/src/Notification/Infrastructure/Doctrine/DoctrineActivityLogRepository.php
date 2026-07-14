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
        $queryBuilder = $this->em()->createQueryBuilder()
            ->select('log')
            ->from(ActivityLog::class, 'log')
            ->leftJoin('log.collectionEntry', 'entry')
            ->orderBy('log.startedAt', 'DESC');

        if (isset($filters['eventType'])) {
            $queryBuilder->andWhere('log.eventType = :eventType')
               ->setParameter('eventType', $filters['eventType']);
        }

        if (isset($filters['status'])) {
            $queryBuilder->andWhere('log.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (isset($filters['collectionEntryId'])) {
            $queryBuilder->andWhere('entry.id = :collectionEntryId')
               ->setParameter('collectionEntryId', $filters['collectionEntryId']);
        }

        if (isset($filters['ownerId'])) {
            $queryBuilder->andWhere('log.owner = :ownerId')
               ->setParameter('ownerId', $filters['ownerId']);
        }

        if (isset($filters['from'])) {
            $queryBuilder->andWhere('log.startedAt >= :fromDate')
               ->setParameter('fromDate', $filters['from']);
        }

        if (isset($filters['to'])) {
            $queryBuilder->andWhere('log.startedAt <= :toDate')
               ->setParameter('toDate', $filters['to']);
        }

        if (isset($filters['search'])) {
            $queryBuilder->andWhere(
                'LOWER(log.sourceName) LIKE :search'
                . ' OR LOWER(log.errorMessage) LIKE :search'
                . " OR LOWER(JSON_GET_TEXT(log.metadata, 'path')) LIKE :search",
            )->setParameter('search', '%' . mb_strtolower($filters['search']) . '%');
        }

        $total = (clone $queryBuilder)->select('COUNT(log.id)')->resetDQLPart('orderBy')
            ->getQuery()->getSingleScalarResult();

        $items = $queryBuilder
            ->addSelect('entry')
            ->leftJoin('log.owner', 'owner')
            ->addSelect('owner')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => (int) $total];
    }

    public function deleteOlderThan(DateTimeImmutable $before): int
    {
        return (int) $this->em()->createQueryBuilder()
            ->delete(ActivityLog::class, 'log')
            ->where('log.startedAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    public function countRecentErrors(int $windowMinutes = 10): int
    {
        $since = new DateTimeImmutable("-{$windowMinutes} minutes");

        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM activity_logs
            WHERE status = :status
              AND event_type = :eventType
              AND started_at >= :since
              AND (metadata->>'external_api_failure' IS DISTINCT FROM 'true')
        SQL;

        return (int) $this->em()->getConnection()->executeQuery($sql, [
            'status'    => 'error',
            'eventType' => 'worker_failure',
            'since'     => $since->format('Y-m-d H:i:s.uP'),
        ])->fetchOne();
    }
}
