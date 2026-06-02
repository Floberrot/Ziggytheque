<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Manga;

use App\Manga\Domain\ScanResultPublisherInterface;

final class InMemoryScanResultPublisher implements ScanResultPublisherInterface
{
    /** @var array<int, array{sessionId: string, isbn: string}> */
    public array $published = [];

    public function publish(string $sessionId, string $isbn): void
    {
        $this->published[] = ['sessionId' => $sessionId, 'isbn' => $isbn];
    }
}
