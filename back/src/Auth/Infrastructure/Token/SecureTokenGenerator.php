<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Token;

use App\Auth\Domain\Service\TokenGeneratorInterface;

final readonly class SecureTokenGenerator implements TokenGeneratorInterface
{
    public function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
