<?php

declare(strict_types=1);

namespace App\Manga\Domain;

use App\Manga\Domain\Exception\InvalidScanTokenException;

interface ScanTokenIssuerInterface
{
    public function issue(string $sessionId, int $ttlSeconds): string;

    /** @throws InvalidScanTokenException */
    public function verify(string $token): string;
}
