<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Doctrine;

use App\Auth\Domain\Service\AdminBackfillServiceInterface;
use App\Auth\Domain\ValueObject\BackfillReport;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAdminBackfillService implements AdminBackfillServiceInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function assignAllOrphans(string $adminId): BackfillReport
    {
        $connection = $this->entityManager->getConnection();

        $collectionEntries = (int) $connection->executeStatement(
            'UPDATE collection_entries SET owner_id = :id WHERE owner_id IS NULL',
            ['id' => $adminId],
        );

        $notifications = (int) $connection->executeStatement(
            'UPDATE notifications SET owner_id = :id WHERE owner_id IS NULL',
            ['id' => $adminId],
        );

        $articles = (int) $connection->executeStatement(
            'UPDATE articles SET owner_id = :id WHERE owner_id IS NULL',
            ['id' => $adminId],
        );

        $activityLogs = (int) $connection->executeStatement(
            'UPDATE activity_logs SET owner_id = :id WHERE owner_id IS NULL',
            ['id' => $adminId],
        );

        return new BackfillReport(
            collectionEntries: $collectionEntries,
            notifications: $notifications,
            articles: $articles,
            activityLogs: $activityLogs,
        );
    }
}
