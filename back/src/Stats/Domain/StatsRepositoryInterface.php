<?php

declare(strict_types=1);

namespace App\Stats\Domain;

interface StatsRepositoryInterface
{
    /** @return array<string, mixed> */
    public function getStats(): array;
}
