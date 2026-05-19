<?php

declare(strict_types=1);

namespace App\Auth\Domain;

enum UserRoleEnum: string
{
    case User  = 'ROLE_USER';
    case Admin = 'ROLE_ADMIN';
}
