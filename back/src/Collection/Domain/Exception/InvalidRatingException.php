<?php

declare(strict_types=1);

namespace App\Collection\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidRatingException extends DomainException
{
    public function __construct(int $value)
    {
        parent::__construct(
            sprintf('Rating must be between 0 and 10 (half-points × 2), got %d.', $value),
        );
    }

    public function getHttpStatusCode(): int
    {
        return 422;
    }
}
