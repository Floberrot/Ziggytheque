<?php

declare(strict_types=1);

namespace App\Collection\Application\Remove;

final readonly class RemoveFromCollectionCommand
{
    public function __construct(public string $id)
    {
    }
}
