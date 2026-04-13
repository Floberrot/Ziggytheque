<?php

declare(strict_types=1);

namespace App\Auth\Application\Gate;

final readonly class GateCommand
{
    public function __construct(public string $password)
    {
    }
}
