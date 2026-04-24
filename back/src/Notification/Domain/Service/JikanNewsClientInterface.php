<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

use App\Collection\Domain\CollectionEntry;

interface JikanNewsClientInterface
{
    public function fetch(string $malId, CollectionEntry $entry): JikanFetchResult;
}
