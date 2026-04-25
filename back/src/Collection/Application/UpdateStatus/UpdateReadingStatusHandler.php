<?php

declare(strict_types=1);

namespace App\Collection\Application\UpdateStatus;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\ReadingStatusEnum;
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
        try {
            $entry = $this->repository->findById($command->id);

            if ($entry === null) {
                throw new NotFoundException('CollectionEntry', $command->id);
            }

            $entry->readingStatus = ReadingStatusEnum::from($command->status);
            $this->repository->save($entry);

            $this->eventBus->publish(new UpdateReadingStatusSucceededEvent(
                collectionEntryId: $entry->id,
                status: $command->status,
            ));
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
