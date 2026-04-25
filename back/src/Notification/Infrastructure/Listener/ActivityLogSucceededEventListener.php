<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Listener;

use App\Notification\Domain\Service\ActivityLogEventHandler;
use App\Shared\Domain\Event\SucceededEventInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Single listener for ALL Succeeded domain events across every module.
 *
 * Finds the ActivityLog by correlationId and marks it as success.
 * Replaces N per-event Succeeded listeners — zero duplication, Open/Closed.
 */
#[AsEventListener(event: SucceededEventInterface::class)]
final readonly class ActivityLogSucceededEventListener
{
    public function __construct(private ActivityLogEventHandler $handler)
    {
    }

    public function __invoke(SucceededEventInterface $event): void
    {
        $this->handler->handleSucceededEvent($event);
    }
}
