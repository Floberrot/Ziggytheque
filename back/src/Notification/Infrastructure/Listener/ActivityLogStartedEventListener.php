<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Listener;

use App\Notification\Domain\Service\ActivityLogEventHandler;
use App\Shared\Domain\Event\StartedEventInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Single listener for ALL Started domain events across every module.
 *
 * Receives any event implementing StartedEventInterface thanks to SymfonyEventBus
 * dispatching to interface names. Creates the ActivityLog entry with status 'running'.
 *
 * Replaces N per-event Started listeners — zero duplication, Open/Closed.
 */
#[AsEventListener(event: StartedEventInterface::class)]
final readonly class ActivityLogStartedEventListener
{
    public function __construct(private ActivityLogEventHandler $handler)
    {
    }

    public function __invoke(StartedEventInterface $event): void
    {
        $this->handler->handleStartedEvent($event, $this->handler->detectEventTypeEnum($event));
    }
}
