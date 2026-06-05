<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class RateLimitExceededException extends DomainException
{
    public function getHttpStatusCode(): int
    {
        return 429;
    }
}
