<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class UserNotFoundException extends DomainException
{
    public function __construct(string $identifier)
    {
        parent::__construct(sprintf('User "%s" not found.', $identifier));
    }

    public function getHttpStatusCode(): int
    {
        return 404;
    }
}
