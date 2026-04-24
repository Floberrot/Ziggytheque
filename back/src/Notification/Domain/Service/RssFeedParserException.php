<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

use RuntimeException;

final class RssFeedParserException extends RuntimeException
{
    public static function httpError(int $statusCode): self
    {
        return new self("HTTP {$statusCode}");
    }

    public static function invalidXml(string $url): self
    {
        return new self("Invalid XML from {$url}");
    }
}
