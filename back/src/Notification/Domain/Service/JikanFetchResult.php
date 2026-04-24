<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

final readonly class JikanFetchResult
{
    public function __construct(
        public int $newCount,
        public int $itemsReceived,
    ) {
    }
}
