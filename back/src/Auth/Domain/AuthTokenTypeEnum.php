<?php

declare(strict_types=1);

namespace App\Auth\Domain;

enum AuthTokenTypeEnum: string
{
    case EmailVerification = 'email_verification';
    case PasswordReset     = 'password_reset';
}
