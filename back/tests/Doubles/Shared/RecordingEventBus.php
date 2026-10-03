<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Shared;

use App\Shared\Application\Bus\EventBusInterface;
use LogicException;

/** Keeps every published event, in order, so a test can read what a handler announced. */
final class RecordingEventBus implements EventBusInterface
{
    /** @var list<object> */
    public array $events = [];

    public function publish(object ...$events): void
    {
        foreach ($events as $event) {
            $this->events[] = $event;
        }
    }

    /** @return list<class-string> */
    public function eventClasses(): array
    {
        return array_map(static fn (object $event): string => $event::class, $this->events);
    }

    /**
     * @template T of object
     * @param  class-string<T> $eventClass
     * @return list<T>
     */
    public function eventsOf(string $eventClass): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (object $event): bool => $event instanceof $eventClass,
        ));
    }

    /**
     * @template T of object
     * @param  class-string<T> $eventClass
     * @return T
     */
    public function first(string $eventClass): object
    {
        foreach ($this->events as $event) {
            if ($event instanceof $eventClass) {
                return $event;
            }
        }

        throw new LogicException(sprintf('No %s was published.', $eventClass));
    }
}
