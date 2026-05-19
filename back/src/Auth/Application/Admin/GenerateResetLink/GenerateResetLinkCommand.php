<?php

declare(strict_types=1);

namespace App\Auth\Application\Admin\GenerateResetLink;

final readonly class GenerateResetLinkCommand
{
    public function __construct(public string $userId)
    {
    }
}
