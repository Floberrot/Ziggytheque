<?php

declare(strict_types=1);

namespace App\Auth\Application\Admin\ApproveUser;

final readonly class ApproveUserCommand
{
    public function __construct(public string $userId)
    {
    }
}
