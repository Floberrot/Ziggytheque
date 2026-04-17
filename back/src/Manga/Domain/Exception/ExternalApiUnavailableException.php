<?php

declare(strict_types=1);

namespace App\Manga\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class ExternalApiUnavailableException extends DomainException
{
    public function __construct()
    {
        parent::__construct('All cover providers are currently unavailable. Please try again later.');
    }

    public function getHttpStatusCode(): int
    {
        return 503;
    }
}
