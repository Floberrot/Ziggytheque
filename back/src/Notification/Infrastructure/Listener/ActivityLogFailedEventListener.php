<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Listener;

use App\Notification\Domain\Service\ActivityLogEventHandler;
use App\Shared\Domain\Event\FailedEventInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Single listener for ALL Failed domain events across every module.
 *
 * Finds the ActivityLog by correlationId and marks it as error.
 * Replaces N per-event Failed listeners — zero duplication, Open/Closed.
 */
#[AsEventListener(event: FailedEventInterface::class)]
final readonly class ActivityLogFailedEventListener
{
    public function __construct(private ActivityLogEventHandler $handler)
    {
    }

    public function __invoke(FailedEventInterface $event): void
    {
        $this->handler->handleFailedEvent($event);
    }
}
