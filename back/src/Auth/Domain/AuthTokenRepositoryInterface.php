<?php

declare(strict_types=1);

namespace App\Auth\Domain;

interface AuthTokenRepositoryInterface
{
    public function save(AuthToken $token): void;

    public function findValidByHash(string $tokenHash, AuthTokenTypeEnum $type): ?AuthToken;
}
