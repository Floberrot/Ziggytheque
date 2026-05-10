<?php

declare(strict_types=1);

namespace App\Manga\Domain;

final readonly class CoverBatchProgressEvent
{
    public function __construct(
        public string $type,
        public string $batchId,
        public int $total,
        public int $processed,
        public int $resolved,
        public int $failed,
        public int $skipped,
        public ?string $volumeId = null,
        public ?int $volumeNumber = null,
        public ?string $coverUrl = null,
        public ?string $reason = null,
    ) {
    }

    public static function started(string $batchId, int $total): self
    {
        return new self(
            type: 'batch_started',
            batchId: $batchId,
            total: $total,
            processed: 0,
            resolved: 0,
            failed: 0,
            skipped: 0,
        );
    }

    public static function volumeResolved(
        string $batchId,
        int $total,
        int $processed,
        int $resolved,
        int $failed,
        int $skipped,
        string $volumeId,
        int $volumeNumber,
        string $coverUrl,
    ): self {
        return new self(
            type: 'volume_resolved',
            batchId: $batchId,
            total: $total,
            processed: $processed,
            resolved: $resolved,
            failed: $failed,
            skipped: $skipped,
            volumeId: $volumeId,
            volumeNumber: $volumeNumber,
            coverUrl: $coverUrl,
        );
    }

    public static function volumeFailed(
        string $batchId,
        int $total,
        int $processed,
        int $resolved,
        int $failed,
        int $skipped,
        string $volumeId,
        int $volumeNumber,
        string $reason,
    ): self {
        return new self(
            type: 'volume_failed',
            batchId: $batchId,
            total: $total,
            processed: $processed,
            resolved: $resolved,
            failed: $failed,
            skipped: $skipped,
            volumeId: $volumeId,
            volumeNumber: $volumeNumber,
            reason: $reason,
        );
    }

    public static function completed(
        string $batchId,
        int $total,
        int $resolved,
        int $failed,
        int $skipped,
    ): self {
        return new self(
            type: 'batch_completed',
            batchId: $batchId,
            total: $total,
            processed: $resolved + $failed + $skipped,
            resolved: $resolved,
            failed: $failed,
            skipped: $skipped,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'batchId' => $this->batchId,
            'total' => $this->total,
            'processed' => $this->processed,
            'resolved' => $this->resolved,
            'failed' => $this->failed,
            'skipped' => $this->skipped,
        ];

        if ($this->volumeId !== null) {
            $data['volumeId'] = $this->volumeId;
        }

        if ($this->volumeNumber !== null) {
            $data['volumeNumber'] = $this->volumeNumber;
        }

        if ($this->coverUrl !== null) {
            $data['coverUrl'] = $this->coverUrl;
        }

        if ($this->reason !== null) {
            $data['reason'] = $this->reason;
        }

        return $data;
    }
}
