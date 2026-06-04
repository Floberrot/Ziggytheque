<?php

declare(strict_types=1);

namespace App\Share\Application\GetSnapshot;

final readonly class GetShareSnapshotQuery
{
    public function __construct(public string $token)
    {
    }
}
