<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface ScanResultPublisherInterface
{
    public function publish(string $sessionId, string $isbn): void;
}
