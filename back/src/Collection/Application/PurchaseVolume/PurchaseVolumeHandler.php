<?php

declare(strict_types=1);

namespace App\Collection\Application\PurchaseVolume;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class PurchaseVolumeHandler
{
    public function __construct(private CollectionRepositoryInterface $repository)
    {
    }

    public function __invoke(PurchaseVolumeCommand $command): void
    {
        $entry = $this->repository->findById($command->collectionEntryId);

        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $command->collectionEntryId);
        }

        $volumeEntry = $entry->volumeEntries
            ->filter(fn (VolumeEntry $ve) => $ve->id === $command->volumeEntryId)
            ->first();

        if ($volumeEntry === false) {
            throw new NotFoundException('VolumeEntry', $command->volumeEntryId);
        }

        $volumeEntry->isOwned = true;
        $volumeEntry->isWished = false;

        $this->repository->save($entry);
    }
}
