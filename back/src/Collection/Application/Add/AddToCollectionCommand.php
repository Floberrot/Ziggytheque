<?php

declare(strict_types=1);

namespace App\Collection\Application\Add;

final readonly class AddToCollectionCommand
{
    public function __construct(public string $mangaId)
    {
    }
}
