<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Remove;

use App\Shared\Domain\Exception\NotFoundException;
use App\Wishlist\Domain\WishlistRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveWishlistItemHandler
{
    public function __construct(private WishlistRepositoryInterface $repository)
    {
    }

    public function __invoke(RemoveWishlistItemCommand $command): void
    {
        $item = $this->repository->findById($command->id);

        if ($item === null) {
            throw new NotFoundException('WishlistItem', $command->id);
        }

        $this->repository->delete($item);
    }
}
