<?php

declare(strict_types=1);

namespace App\Collection\Application\GetDetail;

final readonly class GetCollectionDetailQuery
{
    public function __construct(public string $id)
    {
    }
}
