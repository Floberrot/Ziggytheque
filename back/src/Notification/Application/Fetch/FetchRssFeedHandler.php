<?php

declare(strict_types=1);

namespace App\Notification\Application\Fetch;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\FollowedSeries;
use App\Notification\Domain\Service\RssArticleCollector;
use App\Notification\Domain\Service\RssFeedParserInterface;
use App\Notification\Shared\Event\RssFetchFailedEvent;
use App\Notification\Shared\Event\RssFetchStartedEvent;
use App\Notification\Shared\Event\RssFetchSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Downloads one feed once, then links its items to every followed series of the run.
 * Each series keeps its own journal line (started, then succeeded or failed).
 */
#[AsMessageHandler]
final readonly class FetchRssFeedHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private RssFeedParserInterface $rssFeedParser,
        private RssArticleCollector $articleCollector,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(FetchRssFeedMessage $message): void
    {
        $fetches = $this->startFetches($message);
        if ($fetches === []) {
            return;
        }

        try {
            $feedItems = $this->rssFeedParser->parse($message->feedUrl);
        } catch (Throwable $exception) {
            foreach ($fetches as [$started, $entry]) {
                $this->publishFailure($message, $started, $entry, $exception);
            }

            // Retried by the worker: the feed is downloaded again for every series.
            throw $exception;
        }

        foreach ($fetches as [$started, $entry, $followedSeries]) {
            try {
                $result = $this->articleCollector->collect($feedItems, $entry, $followedSeries->mangaTitle);
            } catch (Throwable $exception) {
                // One series failing never costs the others their articles.
                $this->publishFailure($message, $started, $entry, $exception);
                continue;
            }

            $this->eventBus->publish(new RssFetchSucceededEvent(
                correlationId: $started->correlationId,
                feedName: $message->feedName,
                collectionEntryId: $entry->id,
                newCount: $result->newCount,
                itemsScanned: $result->itemsScanned,
                mangaTitle: $entry->manga->title,
                mangaCoverUrl: $entry->manga->coverUrl,
            ));
        }
    }

    /**
     * Opens a journal line for each followed series still in a collection (one removed
     * since the crawl was planned has nothing to be linked to).
     *
     * @return list<array{RssFetchStartedEvent, CollectionEntry, FollowedSeries}>
     */
    private function startFetches(FetchRssFeedMessage $message): array
    {
        $fetches = [];

        foreach ($message->followedSeries as $followedSeries) {
            $entry = $this->collectionRepository->findById($followedSeries->collectionEntryId);
            if ($entry === null) {
                continue;
            }

            $started = new RssFetchStartedEvent(
                feedName: $message->feedName,
                feedUrl: $message->feedUrl,
                mangaTitle: $followedSeries->mangaTitle,
                collectionEntryId: $entry->id,
            );
            $this->eventBus->publish($started);
            $fetches[] = [$started, $entry, $followedSeries];
        }

        return $fetches;
    }

    private function publishFailure(
        FetchRssFeedMessage $message,
        RssFetchStartedEvent $started,
        CollectionEntry $entry,
        Throwable $exception,
    ): void {
        $this->eventBus->publish(new RssFetchFailedEvent(
            correlationId: $started->correlationId,
            feedName: $message->feedName,
            collectionEntryId: $entry->id,
            error: $exception->getMessage(),
            exceptionClass: $exception::class,
        ));
    }
}
