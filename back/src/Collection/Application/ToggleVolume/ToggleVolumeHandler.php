<?php

declare(strict_types=1);

namespace App\Collection\Application\ToggleVolume;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\ReadingStatusEnum;
use App\Collection\Domain\VolumeEntry;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ToggleVolumeHandler
{
    public function __construct(private CollectionRepositoryInterface $repository)
    {
    }

    public function __invoke(ToggleVolumeCommand $command): void
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

        if ($command->field === 'isOwned') {
            $volumeEntry->isOwned = !$volumeEntry->isOwned;
            if ($volumeEntry->isOwned) {
                $volumeEntry->isWished = false;
                $volumeEntry->isAnnounced = false;
            }
        } elseif ($command->field === 'isRead') {
            $volumeEntry->isRead = !$volumeEntry->isRead;
        } elseif ($command->field === 'isWished') {
            $volumeEntry->isWished = !$volumeEntry->isWished;
        } elseif ($command->field === 'isAnnounced') {
            $volumeEntry->isAnnounced = !$volumeEntry->isAnnounced;
        }

        $this->autoUpdateReadingStatus($entry);

        $this->repository->save($entry);
    }

    private function autoUpdateReadingStatus(CollectionEntry $entry): void
    {
        // Never override intentional user choices
        if (\in_array($entry->readingStatus, [ReadingStatusEnum::Dropped, ReadingStatusEnum::OnHold], true)) {
            return;
        }

        $total = $entry->volumeEntries->count();

        if ($total === 0) {
            return;
        }

        $ownedCount = $entry->volumeEntries->filter(fn (VolumeEntry $ve) => $ve->isOwned)->count();
        $readCount  = $entry->volumeEntries->filter(fn (VolumeEntry $ve) => $ve->isRead)->count();

        $entry->readingStatus = match (true) {
            $readCount === $total            => ReadingStatusEnum::Completed,
            $readCount > 0 || $ownedCount > 0 => ReadingStatusEnum::InProgress,
            default                          => ReadingStatusEnum::NotStarted,
        };
    }
}
