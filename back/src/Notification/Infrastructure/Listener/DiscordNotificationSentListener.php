<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Listener;

use App\Notification\Domain\DiscordNotifierInterface;
use App\Notification\Shared\Event\DiscordNotificationSentEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class DiscordNotificationSentListener
{
    public function __construct(private DiscordNotifierInterface $discord)
    {
    }

    public function __invoke(DiscordNotificationSentEvent $event): void
    {
        $this->discord->sendNewArticles(
            mangaTitle: $event->mangaTitle,
            mangaCoverUrl: $event->mangaCoverUrl,
            count: $event->articleCount,
            articles: $event->articles,
        );
    }
}
