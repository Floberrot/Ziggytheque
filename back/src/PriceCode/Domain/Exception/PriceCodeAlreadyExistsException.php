<?php

declare(strict_types=1);

namespace App\PriceCode\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class PriceCodeAlreadyExistsException extends DomainException
{
    public function __construct(string $code)
    {
        parent::__construct(sprintf('Price code "%s" already exists.', $code));
    }

    public function getHttpStatusCode(): int
    {
        return 409;
    }
}
