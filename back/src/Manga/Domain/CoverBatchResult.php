<?php

declare(strict_types=1);

namespace App\Manga\Domain;

final readonly class CoverBatchResult
{
    public function __construct(
        public int $updated,
        public int $failed,
        public int $skipped,
    ) {
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'updated' => $this->updated,
            'failed' => $this->failed,
            'skipped' => $this->skipped,
        ];
    }
}
