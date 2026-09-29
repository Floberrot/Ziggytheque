<?php

declare(strict_types=1);

namespace App\Manga\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class CatalogueQueryTooShortException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The catalogue search needs at least 2 characters.');
    }

    public function getHttpStatusCode(): int
    {
        return 422;
    }
}
