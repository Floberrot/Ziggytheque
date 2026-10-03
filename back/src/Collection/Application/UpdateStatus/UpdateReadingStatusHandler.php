<?php

declare(strict_types=1);

namespace App\Collection\Application\UpdateStatus;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Shared\Event\UpdateReadingStatusFailedEvent;
use App\Collection\Shared\Event\UpdateReadingStatusStartedEvent;
use App\Collection\Shared\Event\UpdateReadingStatusSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateReadingStatusHandler
{
    public function __construct(
        private CollectionRepositoryInterface $repository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(UpdateReadingStatusCommand $command): void
    {
        $started = new UpdateReadingStatusStartedEvent(
            collectionEntryId: $command->id,
            status: $command->status->value,
        );
        $this->eventBus->publish($started);

        try {
            $entry = $this->repository->findById($command->id);

            if ($entry === null) {
                throw new NotFoundException('CollectionEntry', $command->id);
            }

            $entry->readingStatus = $command->status;
            $this->repository->save($entry);

            $this->eventBus->publish(new UpdateReadingStatusSucceededEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $entry->id,
                status: $command->status->value,
            ));
        } catch (Throwable $exception) {
            $this->eventBus->publish(new UpdateReadingStatusFailedEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $command->id,
                status: $command->status->value,
                error: $exception->getMessage(),
                exceptionClass: $exception::class,
            ));
            throw $exception;
        }
    }
}
