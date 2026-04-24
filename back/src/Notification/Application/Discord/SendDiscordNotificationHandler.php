<?php

declare(strict_types=1);

namespace App\Notification\Application\Discord;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\ArticleRepositoryInterface;
use App\Notification\Shared\Event\DiscordNotificationSentEvent;
use App\Notification\Shared\Event\DiscordNotificationSkippedEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class SendDiscordNotificationHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private ArticleRepositoryInterface $articleRepository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(SendDiscordNotificationMessage $message): void
    {
        $entry = $this->collectionRepository->findById($message->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $message->collectionEntryId);
        }

        if ($entry->lastNotifiedAt !== null) {
            $this->eventBus->publish(new DiscordNotificationSkippedEvent(
                collectionEntryId: $entry->id,
                mangaTitle: $entry->manga->title,
                reason: 'cooldown',
            ));

            return;
        }

        $result   = $this->articleRepository->findPaginated(1, 10, $entry->id);
        $articles = array_map(static fn ($a) => $a->toArray(), $result['items']);

        if ($articles === []) {
            $this->eventBus->publish(new DiscordNotificationSkippedEvent(
                collectionEntryId: $entry->id,
                mangaTitle: $entry->manga->title,
                reason: 'no_articles',
            ));

            return;
        }

        $entry->lastNotifiedAt = new DateTimeImmutable();
        $this->collectionRepository->save($entry);

        $this->eventBus->publish(new DiscordNotificationSentEvent(
            correlationId: Uuid::v4()->toRfc4122(),
            collectionEntryId: $entry->id,
            mangaTitle: $entry->manga->title,
            mangaCoverUrl: $entry->manga->coverUrl,
            articleCount: count($articles),
            articles: $articles,
        ));
    }
}
