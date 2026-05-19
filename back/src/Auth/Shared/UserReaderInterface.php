<?php

declare(strict_types=1);

namespace App\Auth\Shared;

use App\Auth\Shared\Dto\UserDto;

interface UserReaderInterface
{
    public function findById(string $id): ?UserDto;
}
