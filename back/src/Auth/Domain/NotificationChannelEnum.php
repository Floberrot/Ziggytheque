<?php

declare(strict_types=1);

namespace App\Auth\Domain;

enum NotificationChannelEnum: string
{
    case Email   = 'email';
    case Discord = 'discord';
}
