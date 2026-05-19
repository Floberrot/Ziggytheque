<?php

declare(strict_types=1);

namespace App\Auth\Application\Admin\DeleteUser;

final readonly class DeleteUserCommand
{
    public function __construct(public string $userId)
    {
    }
}
