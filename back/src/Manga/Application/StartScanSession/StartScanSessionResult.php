<?php

declare(strict_types=1);

namespace App\Manga\Application\StartScanSession;

final readonly class StartScanSessionResult
{
    public function __construct(
        public string $sessionId,
        public string $mercureUrl,
        public string $subscriberToken,
        public string $topic,
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'sessionId'       => $this->sessionId,
            'mercureUrl'      => $this->mercureUrl,
            'subscriberToken' => $this->subscriberToken,
            'topic'           => $this->topic,
        ];
    }
}
