<?php

declare(strict_types=1);

namespace App\Manga\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class VolumeAlreadyExistsException extends DomainException
{
    public function __construct(int $number)
    {
        parent::__construct(sprintf('Tome %d already exists in this series.', $number));
    }

    public function getHttpStatusCode(): int
    {
        return 409;
    }
}
