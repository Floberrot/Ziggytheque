<?php

declare(strict_types=1);

namespace App\Notification\Application\Fetch;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\Service\JikanNewsClientInterface;
use App\Notification\Shared\Event\JikanFetchFailedEvent;
use App\Notification\Shared\Event\JikanFetchStartedEvent;
use App\Notification\Shared\Event\JikanFetchSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final readonly class FetchJikanNewsHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private JikanNewsClientInterface $jikanNewsClient,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(FetchJikanNewsMessage $message): void
    {
        $entry = $this->collectionRepository->findById($message->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $message->collectionEntryId);
        }

        $started = new JikanFetchStartedEvent(
            malId: $message->malId,
            mangaTitle: $message->mangaTitle,
            collectionEntryId: $entry->id,
        );
        $this->eventBus->publish($started);

        try {
            $result = $this->jikanNewsClient->fetch($message->malId, $entry);

            $this->eventBus->publish(new JikanFetchSucceededEvent(
                correlationId: $started->correlationId,
                malId: $message->malId,
                collectionEntryId: $entry->id,
                newCount: $result->newCount,
                itemsReceived: $result->itemsReceived,
                mangaTitle: $entry->manga->title,
                mangaCoverUrl: $entry->manga->coverUrl,
            ));
        } catch (Throwable $e) {
            $this->eventBus->publish(new JikanFetchFailedEvent(
                correlationId: $started->correlationId,
                malId: $message->malId,
                collectionEntryId: $entry->id,
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
