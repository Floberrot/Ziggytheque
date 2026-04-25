<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\EventBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Event bus backed by Symfony's EventDispatcher.
 *
 * Dispatches each event twice:
 *   1. By its concrete class name → direct per-event listeners receive it.
 *   2. By each App\ interface it implements → generic interface listeners receive it.
 *
 * This enables a single ActivityLogStartedEventListener to handle ALL Started events
 * without any per-event boilerplate, while preserving the option for per-event listeners.
 */
final readonly class SymfonyEventBus implements EventBusInterface
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    public function publish(object ...$events): void
    {
        foreach ($events as $event) {
            // 1. Dispatch to concrete class — for handlers specific to this event type
            $this->dispatcher->dispatch($event, $event::class);

            // 2. Dispatch to every App\ interface — for generic marker-interface listeners
            foreach (class_implements($event) as $interface) {
                if (str_starts_with($interface, 'App\\')) {
                    $this->dispatcher->dispatch($event, $interface);
                }
            }
        }
    }
}
