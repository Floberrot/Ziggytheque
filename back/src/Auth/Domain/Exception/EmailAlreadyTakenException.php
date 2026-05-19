<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class EmailAlreadyTakenException extends DomainException
{
    public function __construct()
    {
        parent::__construct('This email address is already registered.');
    }

    public function getHttpStatusCode(): int
    {
        return 409;
    }
}
