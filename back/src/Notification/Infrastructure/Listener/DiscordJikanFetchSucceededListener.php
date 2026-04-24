<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Listener;

use App\Notification\Application\Discord\SendDiscordNotificationMessage;
use App\Notification\Shared\Event\JikanFetchSucceededEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener]
final readonly class DiscordJikanFetchSucceededListener
{
    public function __construct(private MessageBusInterface $messageBus)
    {
    }

    public function __invoke(JikanFetchSucceededEvent $event): void
    {
        if ($event->newCount === 0) {
            return;
        }
        $this->messageBus->dispatch(new SendDiscordNotificationMessage(
            collectionEntryId: $event->collectionEntryId,
            articleIds: [],
        ));
    }
}
