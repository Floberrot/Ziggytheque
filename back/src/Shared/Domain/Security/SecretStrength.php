<?php

declare(strict_types=1);

namespace App\Shared\Domain\Security;

/**
 * Tells a real secret from a value nobody chose: empty, too short, the placeholder
 * the deploy sync creates (CHANGEME) or a default once committed in `.env`. A feature
 * guarded by a weak secret stays locked instead of trusting it.
 */
final readonly class SecretStrength
{
    /** Passwords typed by a human (monitor, admin gate). */
    public const int MIN_PASSWORD_LENGTH = 12;

    /** Signing keys (Mercure, scan tokens, APP_SECRET). */
    public const int MIN_KEY_LENGTH = 32;

    /**
     * Lower-cased values that must never protect anything — placeholders and the
     * defaults that used to be committed in back/.env.
     *
     * @var list<string>
     */
    private const array KNOWN_DEFAULTS = [
        'changeme', 'change_me', 'replace_here', 'ziggy123', 'monitor', 'ziggy_jwt_passphrase',
        'change_me_to_a_random_string_in_production',
    ];

    /** "!ChangeThisMercurePublisherSecret32c!", "change-me-please"… */
    private const string PLACEHOLDER_PATTERN = '/^!?change[\s_-]?(?:this|me)/i';

    public static function isWeak(string $secret, int $minimumLength): bool
    {
        $trimmed = trim($secret);

        return mb_strlen($trimmed) < $minimumLength
            || in_array(mb_strtolower($trimmed), self::KNOWN_DEFAULTS, true)
            || preg_match(self::PLACEHOLDER_PATTERN, $trimmed) === 1;
    }
}
