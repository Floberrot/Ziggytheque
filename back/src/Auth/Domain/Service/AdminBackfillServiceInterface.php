<?php

declare(strict_types=1);

namespace App\Auth\Domain\Service;

use App\Auth\Domain\ValueObject\BackfillReport;

interface AdminBackfillServiceInterface
{
    public function assignAllOrphans(string $adminId): BackfillReport;
}
