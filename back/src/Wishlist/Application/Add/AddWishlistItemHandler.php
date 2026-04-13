<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Add;

use App\Manga\Domain\MangaRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use App\Wishlist\Domain\WishlistItem;
use App\Wishlist\Domain\WishlistRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AddWishlistItemHandler
{
    public function __construct(
        private WishlistRepositoryInterface $repository,
        private MangaRepositoryInterface $mangaRepository,
    ) {
    }

    public function __invoke(AddWishlistItemCommand $command): string
    {
        $manga = $this->mangaRepository->findById($command->mangaId);

        if ($manga === null) {
            throw new NotFoundException('Manga', $command->mangaId);
        }

        $item = new WishlistItem(id: Uuid::v4()->toRfc4122(), manga: $manga);
        $this->repository->save($item);

        return $item->id;
    }
}
