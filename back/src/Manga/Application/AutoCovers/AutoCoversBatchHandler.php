<?php

declare(strict_types=1);

namespace App\Manga\Application\AutoCovers;

use App\Manga\Domain\CoverBatchProgressEvent;
use App\Manga\Domain\CoverBatchProgressPublisherInterface;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Service\CoverBatchResolver;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AutoCoversBatchHandler
{
    public function __construct(
        private MangaRepositoryInterface $mangaRepository,
        private CoverBatchResolver $coverBatchResolver,
        private CoverBatchProgressPublisherInterface $publisher,
    ) {
    }

    public function __invoke(AutoCoversBatchMessage $message): void
    {
        $manga = $this->mangaRepository->findById($message->mangaId);

        if ($manga === null) {
            return;
        }

        $total = $this->coverBatchResolver->countResolvable($manga, $message->force, $message->volumeIds);

        $this->publisher->publish(CoverBatchProgressEvent::started($message->batchId, $total));

        $result = $this->coverBatchResolver->resolveAll(
            manga: $manga,
            force: $message->force,
            volumeIds: $message->volumeIds,
            publisher: $this->publisher,
            batchId: $message->batchId,
        );

        $this->mangaRepository->save($manga);

        $this->publisher->publish(CoverBatchProgressEvent::completed(
            batchId: $message->batchId,
            total: $total,
            resolved: $result->updated,
            failed: $result->failed,
            skipped: $result->skipped,
        ));
    }
}
