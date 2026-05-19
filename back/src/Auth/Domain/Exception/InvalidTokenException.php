<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidTokenException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Token is invalid, expired, or already used.');
    }

    public function getHttpStatusCode(): int
    {
        return 400;
    }
}
