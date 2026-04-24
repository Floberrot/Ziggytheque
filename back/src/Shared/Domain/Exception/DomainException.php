<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use RuntimeException;

abstract class DomainException extends RuntimeException
{
    abstract public function getHttpStatusCode(): int;
}
