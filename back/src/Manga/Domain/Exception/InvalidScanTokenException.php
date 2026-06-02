<?php

declare(strict_types=1);

namespace App\Manga\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidScanTokenException extends DomainException
{
    public function getHttpStatusCode(): int
    {
        return 410;
    }
}
