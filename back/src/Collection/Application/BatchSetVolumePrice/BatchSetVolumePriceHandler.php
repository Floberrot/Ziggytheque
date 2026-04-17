<?php

declare(strict_types=1);

namespace App\Collection\Application\BatchSetVolumePrice;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Manga\Domain\Volume;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class BatchSetVolumePriceHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
    ) {
    }

    public function __invoke(BatchSetVolumePriceCommand $command): void
    {
        $entry = $this->collectionRepository->findById($command->collectionEntryId);

        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $command->collectionEntryId);
        }

        foreach ($entry->manga->volumes as $volume) {
            /** @var Volume $volume */
            $volume->price = $command->price;
        }

        $this->collectionRepository->save($entry);
    }
}
