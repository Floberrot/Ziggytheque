<?php

declare(strict_types=1);

namespace App\Collection\Application\BatchSetVolumePrice;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Shared\Event\BatchSetVolumePriceFailedEvent;
use App\Collection\Shared\Event\BatchSetVolumePriceStartedEvent;
use App\Collection\Shared\Event\BatchSetVolumePriceSucceededEvent;
use App\Manga\Domain\Volume;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class BatchSetVolumePriceHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(BatchSetVolumePriceCommand $command): void
    {
        $started = new BatchSetVolumePriceStartedEvent(
            collectionEntryId: $command->collectionEntryId,
            price: $command->price,
            count: 0,
        );
        $this->eventBus->publish($started);

        try {
            $entry = $this->collectionRepository->findById($command->collectionEntryId);

            if ($entry === null) {
                throw new NotFoundException('CollectionEntry', $command->collectionEntryId);
            }

            foreach ($entry->manga->volumes as $volume) {
                /** @var Volume $volume */
                $volume->price = $command->price;
            }

            $count = $entry->manga->volumes->count();

            $this->collectionRepository->save($entry);

            $this->eventBus->publish(new BatchSetVolumePriceSucceededEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $entry->id,
                price: $command->price,
                count: $count,
            ));
        } catch (Throwable $e) {
            $this->eventBus->publish(new BatchSetVolumePriceFailedEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $command->collectionEntryId,
                price: $command->price,
                count: 0,
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
