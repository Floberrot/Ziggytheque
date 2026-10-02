<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * A link a user may click: http(s) only. A feed or a scraped page can carry
 * `javascript:` or `data:` URLs, which must never reach an href.
 */
final readonly class WebLink
{
    public static function isWebLink(?string $url): bool
    {
        if ($url === null) {
            return false;
        }

        $scheme = parse_url(trim($url), PHP_URL_SCHEME);
        $host   = parse_url(trim($url), PHP_URL_HOST);

        return is_string($scheme)
            && in_array(strtolower($scheme), ['http', 'https'], true)
            && is_string($host)
            && $host !== '';
    }
}
