<?php

declare(strict_types=1);

namespace App\Manga\Application\Scan;

final readonly class ScanSessionResult
{
    public function __construct(
        public string $sessionId,
        public string $scanToken,
        public string $mercureUrl,
        public string $subscriberToken,
        public string $topic,
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'sessionId' => $this->sessionId,
            'scanToken' => $this->scanToken,
            'mercureUrl' => $this->mercureUrl,
            'subscriberToken' => $this->subscriberToken,
            'topic' => $this->topic,
        ];
    }
}
