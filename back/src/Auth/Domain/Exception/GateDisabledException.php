<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/** GATE_PASSWORD is empty, a placeholder or too short: the admin unlock stays closed. */
final class GateDisabledException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Admin unlock is disabled until GATE_PASSWORD is set to a strong value.');
    }

    public function getHttpStatusCode(): int
    {
        return 503;
    }
}
