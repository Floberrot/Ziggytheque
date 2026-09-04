<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDiscordWebhookUrlException;

/**
 * A Discord webhook endpoint the application is allowed to POST to.
 *
 * The URL is supplied by the user (notification preferences), and the server
 * calls it server-side, so an unvalidated value is a server-side request
 * forgery vector: it could name an internal host, a cloud metadata endpoint, or
 * any third-party service. Only Discord's own webhook endpoints are accepted.
 */
final readonly class DiscordWebhookUrl
{
    /** Discord serves webhooks from these hosts only. */
    private const array ALLOWED_HOSTS = [
        'discord.com',
        'canary.discord.com',
        'ptb.discord.com',
        'discordapp.com',
    ];

    private const string PATH_PREFIX = '/api/webhooks/';

    private function __construct(public string $value)
    {
    }

    /** @throws InvalidDiscordWebhookUrlException */
    public static function fromString(string $url): self
    {
        if (!self::isValid($url)) {
            throw new InvalidDiscordWebhookUrlException();
        }

        return new self($url);
    }

    public static function isValid(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return false;
        }

        // A userinfo component makes `https://discord.com@attacker.example/…`
        // read as a Discord URL while resolving somewhere else entirely.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['port'])) {
            return false;
        }

        if (!in_array(strtolower($parts['host'] ?? ''), self::ALLOWED_HOSTS, true)) {
            return false;
        }

        return str_starts_with($parts['path'] ?? '', self::PATH_PREFIX);
    }
}
