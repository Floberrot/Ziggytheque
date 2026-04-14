<?php

declare(strict_types=1);

namespace App\Collection\Application\WishlistRemaining;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class WishlistRemainingHandler
{
    public function __construct(private CollectionRepositoryInterface $repository)
    {
    }

    public function __invoke(WishlistRemainingCommand $command): void
    {
        $entry = $this->repository->findById($command->collectionEntryId);

        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $command->collectionEntryId);
        }

        foreach ($entry->volumeEntries as $ve) {
            /** @var VolumeEntry $ve */
            if (!$ve->isOwned) {
                $ve->isWishlisted = true;
            }
        }

        $this->repository->save($entry);
    }
}
