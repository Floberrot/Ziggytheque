<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface CoverBatchProgressPublisherInterface
{
    public function publish(CoverBatchProgressEvent $event): void;
}
