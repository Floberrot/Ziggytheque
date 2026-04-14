<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Purchase;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class PurchaseWishlistItemHandler
{
    public function __construct(private CollectionRepositoryInterface $collectionRepository)
    {
    }

    public function __invoke(PurchaseWishlistItemCommand $command): void
    {
        $entry = $this->collectionRepository->findById($command->id);

        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $command->id);
        }

        foreach ($entry->volumeEntries as $ve) {
            /** @var VolumeEntry $ve */
            if ($ve->isWishlisted && !$ve->isOwned) {
                $ve->isOwned = true;
                $ve->isWishlisted = false;
            }
        }

        $this->collectionRepository->save($entry);
    }
}
