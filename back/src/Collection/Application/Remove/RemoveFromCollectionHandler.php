<?php

declare(strict_types=1);

namespace App\Collection\Application\Remove;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Shared\Event\RemoveFromCollectionFailedEvent;
use App\Collection\Shared\Event\RemoveFromCollectionStartedEvent;
use App\Collection\Shared\Event\RemoveFromCollectionSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveFromCollectionHandler
{
    public function __construct(
        private CollectionRepositoryInterface $repository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(RemoveFromCollectionCommand $command): void
    {
        $started = new RemoveFromCollectionStartedEvent(
            collectionEntryId: $command->id,
        );
        $this->eventBus->publish($started);

        try {
            $entry = $this->repository->findById($command->id);

            if ($entry === null) {
                throw new NotFoundException('CollectionEntry', $command->id);
            }

            $this->repository->delete($entry);

            $this->eventBus->publish(new RemoveFromCollectionSucceededEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $entry->id,
            ));
        } catch (Throwable $e) {
            $this->eventBus->publish(new RemoveFromCollectionFailedEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $command->id,
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
