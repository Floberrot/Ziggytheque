<?php

declare(strict_types=1);

namespace App\Notification\Application\Fetch;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\Service\JikanArticleCollector;
use App\Notification\Domain\Service\JikanNewsClientInterface;
use App\Notification\Shared\Event\JikanFetchFailedEvent;
use App\Notification\Shared\Event\JikanFetchStartedEvent;
use App\Notification\Shared\Event\JikanFetchSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Downloads the Jikan news of one MyAnimeList series once, then links them to every
 * followed copy of that series. Each copy keeps its own journal line (started, then
 * succeeded or failed).
 */
#[AsMessageHandler]
final readonly class FetchJikanNewsHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private JikanNewsClientInterface $jikanNewsClient,
        private JikanArticleCollector $articleCollector,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(FetchJikanNewsMessage $message): void
    {
        $fetches = $this->startFetches($message);
        if ($fetches === []) {
            return;
        }

        try {
            $newsItems = $this->jikanNewsClient->fetchNews($message->malId);
        } catch (Throwable $exception) {
            foreach ($fetches as [$started, $entry]) {
                $this->publishFailure($message, $started, $entry, $exception);
            }

            // Retried by the worker: the news are downloaded again for every copy.
            throw $exception;
        }

        foreach ($fetches as [$started, $entry]) {
            try {
                $result = $this->articleCollector->collect($newsItems, $entry);
            } catch (Throwable $exception) {
                // One copy failing never costs the others their articles.
                $this->publishFailure($message, $started, $entry, $exception);
                continue;
            }

            $this->eventBus->publish(new JikanFetchSucceededEvent(
                correlationId: $started->correlationId,
                malId: $message->malId,
                collectionEntryId: $entry->id,
                newCount: $result->newCount,
                itemsReceived: $result->itemsReceived,
                mangaTitle: $entry->manga->title,
                mangaCoverUrl: $entry->manga->coverUrl,
            ));
        }
    }

    /**
     * Opens a journal line for each followed copy still in a collection (one removed
     * since the crawl was planned has nothing to be linked to).
     *
     * @return list<array{JikanFetchStartedEvent, CollectionEntry}>
     */
    private function startFetches(FetchJikanNewsMessage $message): array
    {
        $fetches = [];

        foreach ($message->followedSeries as $followedSeries) {
            $entry = $this->collectionRepository->findById($followedSeries->collectionEntryId);
            if ($entry === null) {
                continue;
            }

            $started = new JikanFetchStartedEvent(
                malId: $message->malId,
                mangaTitle: $followedSeries->mangaTitle,
                collectionEntryId: $entry->id,
            );
            $this->eventBus->publish($started);
            $fetches[] = [$started, $entry];
        }

        return $fetches;
    }

    private function publishFailure(
        FetchJikanNewsMessage $message,
        JikanFetchStartedEvent $started,
        CollectionEntry $entry,
        Throwable $exception,
    ): void {
        $this->eventBus->publish(new JikanFetchFailedEvent(
            correlationId: $started->correlationId,
            malId: $message->malId,
            collectionEntryId: $entry->id,
            error: $exception->getMessage(),
            exceptionClass: $exception::class,
        ));
    }
}
