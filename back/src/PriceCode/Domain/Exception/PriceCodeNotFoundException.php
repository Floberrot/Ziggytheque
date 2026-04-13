<?php

declare(strict_types=1);

namespace App\PriceCode\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class PriceCodeNotFoundException extends DomainException
{
    public function __construct(string $code)
    {
        parent::__construct(sprintf('Price code "%s" not found.', $code));
    }

    public function getHttpStatusCode(): int
    {
        return 404;
    }
}
