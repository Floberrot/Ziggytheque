<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class InvalidDiscordWebhookUrlException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'L\'URL du webhook Discord doit être une URL https://discord.com/api/webhooks/... valide.',
        );
    }

    public function getHttpStatusCode(): int
    {
        return 422;
    }
}
