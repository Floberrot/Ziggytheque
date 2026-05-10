<?php

declare(strict_types=1);

namespace App\Manga\Application\AutoCovers;

final readonly class StartCoverBatchResult
{
    public function __construct(
        public string $batchId,
        public string $mercureUrl,
        public string $subscriberToken,
        public string $topic,
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'batchId' => $this->batchId,
            'mercureUrl' => $this->mercureUrl,
            'subscriberToken' => $this->subscriberToken,
            'topic' => $this->topic,
        ];
    }
}
