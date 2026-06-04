<?php

declare(strict_types=1);

namespace App\Share\Domain;

interface ShareSnapshotRepositoryInterface
{
    public function save(ShareSnapshot $snapshot): void;

    public function findByToken(string $token): ?ShareSnapshot;
}
