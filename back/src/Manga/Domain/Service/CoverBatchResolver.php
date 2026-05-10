<?php

declare(strict_types=1);

namespace App\Manga\Domain\Service;

use App\Manga\Domain\CoverBatchProgressEvent;
use App\Manga\Domain\CoverBatchProgressPublisherInterface;
use App\Manga\Domain\CoverBatchResult;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Domain\Volume;
use Throwable;

final readonly class CoverBatchResolver
{
    public function __construct(private MangaCoverProviderInterface $coverProvider)
    {
    }

    /**
     * @param string[]|null $volumeIds Restrict to specific volume IDs; null means all volumes.
     */
    public function resolveAll(
        Manga $manga,
        bool $force,
        ?array $volumeIds,
        ?CoverBatchProgressPublisherInterface $publisher = null,
        ?string $batchId = null,
    ): CoverBatchResult {
        $total = $this->countResolvable($manga, $force, $volumeIds);
        $updated = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($manga->volumes as $volume) {
            if ($volumeIds !== null && !in_array($volume->id, $volumeIds, strict: true)) {
                continue;
            }

            if (!$force && $volume->coverUrl !== null) {
                $skipped++;
                continue;
            }

            $coverDto = null;
            for ($attempt = 0; $attempt < 3; $attempt++) {
                try {
                    $coverDto = $this->resolveCover($manga, $volume);
                    break;
                } catch (Throwable) {
                    if ($attempt === 2) {
                        break; // all retries exhausted — $coverDto stays null
                    }
                }
            }

            if ($coverDto === null) {
                $failed++;

                if ($publisher !== null && $batchId !== null) {
                    $publisher->publish(CoverBatchProgressEvent::volumeFailed(
                        batchId: $batchId,
                        total: $total,
                        processed: $updated + $failed + $skipped,
                        resolved: $updated,
                        failed: $failed,
                        skipped: $skipped,
                        volumeId: $volume->id,
                        volumeNumber: $volume->number,
                        reason: 'not_found',
                    ));
                }

                continue;
            }

            $this->applyCovers($volume, $coverDto->coverUrl, $coverDto->spineUrl, $coverDto->isbn);
            $updated++;

            if ($publisher !== null && $batchId !== null) {
                $publisher->publish(CoverBatchProgressEvent::volumeResolved(
                    batchId: $batchId,
                    total: $total,
                    processed: $updated + $failed + $skipped,
                    resolved: $updated,
                    failed: $failed,
                    skipped: $skipped,
                    volumeId: $volume->id,
                    volumeNumber: $volume->number,
                    coverUrl: $coverDto->coverUrl,
                ));
            }
        }

        return new CoverBatchResult(updated: $updated, failed: $failed, skipped: $skipped);
    }

    /**
     * Count how many volumes will be actively resolved (not skipped).
     *
     * @param string[]|null $volumeIds
     */
    public function countResolvable(Manga $manga, bool $force, ?array $volumeIds): int
    {
        $count = 0;

        foreach ($manga->volumes as $volume) {
            if ($volumeIds !== null && !in_array($volume->id, $volumeIds, strict: true)) {
                continue;
            }

            if (!$force && $volume->coverUrl !== null) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private function resolveCover(Manga $manga, Volume $volume): ?MangaVolumeCoverDto
    {
        return $volume->isbn !== null
            ? ($this->coverProvider->findByIsbn($volume->isbn) ?? $this->coverProvider->findByContext(
                $manga->title,
                $manga->edition,
                $volume->number,
            ))
            : $this->coverProvider->findByContext(
                $manga->title,
                $manga->edition,
                $volume->number,
            );
    }

    private function applyCovers(Volume $volume, string $coverUrl, ?string $spineUrl, ?Isbn $isbn): void
    {
        $volume->coverUrl = $coverUrl;

        if ($spineUrl !== null) {
            $volume->spineUrl = $spineUrl;
        }

        if ($isbn !== null && $volume->isbn === null) {
            $volume->isbn = $isbn;
        }
    }
}
