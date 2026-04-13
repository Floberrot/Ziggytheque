<?php

declare(strict_types=1);

namespace App\Wishlist\Infrastructure\Doctrine;

use App\Wishlist\Domain\WishlistItem;
use App\Wishlist\Domain\WishlistRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineWishlistRepository implements WishlistRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findById(string $id): ?WishlistItem
    {
        return $this->em->find(WishlistItem::class, $id);
    }

    public function findAll(): array
    {
        return $this->em->getRepository(WishlistItem::class)
            ->findBy([], ['addedAt' => 'DESC']);
    }

    public function save(WishlistItem $item): void
    {
        $this->em->persist($item);
        $this->em->flush();
    }

    public function delete(WishlistItem $item): void
    {
        $this->em->remove($item);
        $this->em->flush();
    }
}
