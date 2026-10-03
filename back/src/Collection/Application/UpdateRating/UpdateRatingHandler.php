<?php

declare(strict_types=1);

namespace App\Collection\Application\UpdateRating;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\Exception\InvalidRatingException;
use App\Collection\Shared\Event\UpdateRatingFailedEvent;
use App\Collection\Shared\Event\UpdateRatingStartedEvent;
use App\Collection\Shared\Event\UpdateRatingSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateRatingHandler
{
    public function __construct(
        private CollectionRepositoryInterface $repository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(UpdateRatingCommand $command): void
    {
        $started = new UpdateRatingStartedEvent(
            collectionEntryId: $command->id,
            rating: $command->rating,
        );
        $this->eventBus->publish($started);

        try {
            if ($command->rating < 0 || $command->rating > 10) {
                throw new InvalidRatingException($command->rating);
            }

            $entry = $this->repository->findById($command->id);

            if ($entry === null) {
                throw new NotFoundException('CollectionEntry', $command->id);
            }

            $entry->rating = $command->rating;
            $this->repository->save($entry);

            $this->eventBus->publish(new UpdateRatingSucceededEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $entry->id,
                rating: $command->rating,
            ));
        } catch (Throwable $exception) {
            $this->eventBus->publish(new UpdateRatingFailedEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $command->id,
                rating: $command->rating,
                error: $exception->getMessage(),
                exceptionClass: $exception::class,
            ));
            throw $exception;
        }
    }
}
