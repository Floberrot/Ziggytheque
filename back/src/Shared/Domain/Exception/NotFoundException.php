<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class NotFoundException extends DomainException
{
    public function __construct(string $resource, string $id)
    {
        parent::__construct(sprintf('%s with id "%s" not found.', $resource, $id));
    }

    public function getHttpStatusCode(): int
    {
        return 404;
    }
}
