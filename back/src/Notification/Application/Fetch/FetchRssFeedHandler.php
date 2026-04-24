<?php

declare(strict_types=1);

namespace App\Notification\Application\Fetch;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\Service\RssFeedParserInterface;
use App\Notification\Shared\Event\RssFetchFailedEvent;
use App\Notification\Shared\Event\RssFetchStartedEvent;
use App\Notification\Shared\Event\RssFetchSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final readonly class FetchRssFeedHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private RssFeedParserInterface $rssFeedParser,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(FetchRssFeedMessage $message): void
    {
        $entry = $this->collectionRepository->findById($message->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $message->collectionEntryId);
        }

        $started = new RssFetchStartedEvent(
            feedName: $message->feedName,
            feedUrl: $message->feedUrl,
            mangaTitle: $message->mangaTitle,
            collectionEntryId: $entry->id,
        );
        $this->eventBus->publish($started);

        try {
            $result = $this->rssFeedParser->parse($message->feedUrl, $message->mangaTitle, $entry);

            $this->eventBus->publish(new RssFetchSucceededEvent(
                correlationId: $started->correlationId,
                feedName: $message->feedName,
                collectionEntryId: $entry->id,
                newCount: $result->newCount,
                itemsScanned: $result->itemsScanned,
                mangaTitle: $entry->manga->title,
                mangaCoverUrl: $entry->manga->coverUrl,
            ));
        } catch (Throwable $e) {
            $this->eventBus->publish(new RssFetchFailedEvent(
                correlationId: $started->correlationId,
                feedName: $message->feedName,
                collectionEntryId: $entry->id,
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
