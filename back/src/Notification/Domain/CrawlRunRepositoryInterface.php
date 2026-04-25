<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use DateTimeImmutable;

interface CrawlRunRepositoryInterface
{
    public function create(string $id, DateTimeImmutable $startedAt): void;
}
