<?php

declare(strict_types=1);

namespace App\Manga\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class IsbnNotInCatalogueException extends DomainException
{
    public function __construct(string $isbn)
    {
        parent::__construct(sprintf('No French edition found for ISBN "%s".', $isbn));
    }

    public function getHttpStatusCode(): int
    {
        return 404;
    }
}
