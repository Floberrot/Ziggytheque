<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidGatePasswordException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Invalid gate password.');
    }

    public function getHttpStatusCode(): int
    {
        return 401;
    }
}
