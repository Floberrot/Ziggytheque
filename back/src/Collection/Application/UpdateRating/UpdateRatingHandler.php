<?php

declare(strict_types=1);

namespace App\Collection\Application\UpdateRating;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\Exception\InvalidRatingException;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateRatingHandler
{
    public function __construct(private CollectionRepositoryInterface $repository)
    {
    }

    public function __invoke(UpdateRatingCommand $command): void
    {
        if ($command->rating < 0 || $command->rating > 10) {
            throw new InvalidRatingException($command->rating);
        }

        $entry = $this->repository->findById($command->id);

        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $command->id);
        }

        $entry->rating = $command->rating;
        $this->repository->save($entry);
    }
}
