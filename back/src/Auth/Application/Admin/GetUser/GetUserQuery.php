<?php

declare(strict_types=1);

namespace App\Auth\Application\Admin\GetUser;

final readonly class GetUserQuery
{
    public function __construct(public string $userId)
    {
    }
}
