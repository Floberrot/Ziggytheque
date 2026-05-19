<?php

declare(strict_types=1);

namespace App\Auth\Domain;

enum UserStatusEnum: string
{
    case PendingEmailVerification = 'pending_email_verification';
    case PendingAdminApproval     = 'pending_admin_approval';
    case Active                   = 'active';
    case Disabled                 = 'disabled';
}
