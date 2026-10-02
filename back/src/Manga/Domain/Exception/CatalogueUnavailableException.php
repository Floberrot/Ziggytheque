<?php

declare(strict_types=1);

namespace App\Manga\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * The catalogue could not answer (timeout, 5xx, quota): not the same as "no French
 * edition" — the user must retry, not give up on the book.
 */
final class CatalogueUnavailableException extends DomainException
{
    public function __construct(string $source)
    {
        parent::__construct(sprintf('The %s catalogue is unavailable, try again later.', $source));
    }

    public function getHttpStatusCode(): int
    {
        return 503;
    }
}
