<?php

declare(strict_types=1);

namespace App\Manga\Domain\Exception;

use App\Manga\Domain\Manga;
use App\Shared\Domain\Exception\DomainException;

/** No series has thousands of tomes: a bigger number is a typo or an abuse. */
final class TooManyVolumesException extends DomainException
{
    public function __construct(int $requested)
    {
        parent::__construct(sprintf('A series has at most %d tomes, %d asked.', Manga::MAX_VOLUMES, $requested));
    }

    public function getHttpStatusCode(): int
    {
        return 422;
    }
}
