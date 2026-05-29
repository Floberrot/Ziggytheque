<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Manga;

use App\Manga\Domain\ScanSessionPublisherInterface;

final class InMemoryScanSessionPublisher implements ScanSessionPublisherInterface
{
    /** @var array<int, array{sessionId: string, isbn: string}> */
    public array $published = [];

    public function publishIsbn(string $sessionId, string $isbn): void
    {
        $this->published[] = ['sessionId' => $sessionId, 'isbn' => $isbn];
    }
}
