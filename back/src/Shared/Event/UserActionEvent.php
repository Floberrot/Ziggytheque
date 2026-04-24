<?php

declare(strict_types=1);

namespace App\Shared\Event;

final readonly class UserActionEvent
{
    public function __construct(
        public string $method,
        public string $path,
        public int $statusCode,
        public string $routeName,
        public int $durationMs,
    ) {
    }
}
