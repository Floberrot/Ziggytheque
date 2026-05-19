<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

use App\Auth\Domain\UserStatusEnum;
use App\Shared\Domain\Exception\DomainException;

final class AccountNotActivatedException extends DomainException
{
    public function __construct(UserStatusEnum $status)
    {
        parent::__construct(sprintf('Account is not active (status: %s).', $status->value));
    }

    public function getHttpStatusCode(): int
    {
        return 403;
    }
}
