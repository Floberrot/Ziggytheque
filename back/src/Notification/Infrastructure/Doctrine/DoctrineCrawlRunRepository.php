<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Doctrine;

use App\Notification\Domain\CrawlRunRepositoryInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DoctrineCrawlRunRepository implements CrawlRunRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function create(string $id, DateTimeImmutable $startedAt): void
    {
        $this->connection->insert('crawl_runs', [
            'id'         => $id,
            'status'     => 'running',
            'started_at' => $startedAt->format('Y-m-d H:i:s'),
        ]);
    }
}
