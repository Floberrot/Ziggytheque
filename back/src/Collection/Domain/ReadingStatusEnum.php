<?php

declare(strict_types=1);

namespace App\Collection\Domain;

enum ReadingStatusEnum: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Dropped = 'dropped';
}
