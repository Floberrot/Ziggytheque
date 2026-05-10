<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Manga;

use App\Manga\Domain\CoverBatchProgressEvent;
use App\Manga\Domain\CoverBatchProgressPublisherInterface;

final class InMemoryCoverBatchProgressPublisher implements CoverBatchProgressPublisherInterface
{
    /** @var CoverBatchProgressEvent[] */
    public array $events = [];

    public function publish(CoverBatchProgressEvent $event): void
    {
        $this->events[] = $event;
    }
}
