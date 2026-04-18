<?php

declare(strict_types=1);

namespace App\Collection\Application\ToggleFollow;

final readonly class ToggleFollowCommand
{
    public function __construct(public string $collectionEntryId) {}
}
