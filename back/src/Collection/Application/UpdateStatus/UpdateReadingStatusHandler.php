<?php

declare(strict_types=1);

namespace App\Collection\Application\UpdateStatus;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\ReadingStatusEnum;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateReadingStatusHandler
{
    public function __construct(private CollectionRepositoryInterface $repository)
    {
    }

    public function __invoke(UpdateReadingStatusCommand $command): void
    {
        $entry = $this->repository->findById($command->id);

        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $command->id);
        }

        $entry->readingStatus = ReadingStatusEnum::from($command->status);
        $this->repository->save($entry);
    }
}
