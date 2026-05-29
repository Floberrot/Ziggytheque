<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface ScanSessionPublisherInterface
{
    public function publishIsbn(string $sessionId, string $isbn): void;
}
