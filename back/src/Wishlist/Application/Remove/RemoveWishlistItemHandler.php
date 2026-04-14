<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Remove;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveWishlistItemHandler
{
    public function __construct(private CollectionRepositoryInterface $collectionRepository)
    {
    }

    public function __invoke(RemoveWishlistItemCommand $command): void
    {
        $entry = $this->collectionRepository->findById($command->id);

        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $command->id);
        }

        $ownedCount = $entry->volumeEntries->filter(fn (VolumeEntry $ve) => $ve->isOwned)->count();

        if ($ownedCount === 0) {
            // No owned volumes — remove the entire entry from the system
            $this->collectionRepository->delete($entry);
        } else {
            // Keep the collection entry but clear all wishlist flags
            foreach ($entry->volumeEntries as $ve) {
                /** @var VolumeEntry $ve */
                $ve->isWishlisted = false;
            }
            $this->collectionRepository->save($entry);
        }
    }
}
