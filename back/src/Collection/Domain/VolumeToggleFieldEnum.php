<?php

declare(strict_types=1);

namespace App\Collection\Domain;

enum VolumeToggleFieldEnum: string
{
    case IsOwned    = 'isOwned';
    case IsRead     = 'isRead';
    case IsWished   = 'isWished';
    case IsAnnounced = 'isAnnounced';
}
