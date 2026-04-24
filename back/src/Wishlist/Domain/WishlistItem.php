<?php

declare(strict_types=1);

namespace App\Wishlist\Domain;

use App\Manga\Domain\Manga;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'wishlist_items')]
class WishlistItem
{
    #[ORM\Column]
    public DateTimeImmutable $addedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Manga::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Manga $manga,
        #[ORM\Column]
        public bool $isPurchased = false,
    ) {
        $this->addedAt = new DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'manga' => $this->manga->toArray(),
            'isPurchased' => $this->isPurchased,
            'addedAt' => $this->addedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
