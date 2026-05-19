<?php

declare(strict_types=1);

namespace App\Auth\Domain\Service;

interface TokenGeneratorInterface
{
    /** Returns a URL-safe random token string (plain, not hashed). */
    public function generate(): string;

    public function hash(string $plainToken): string;
}
