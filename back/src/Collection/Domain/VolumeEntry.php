<?php

declare(strict_types=1);

namespace App\Collection\Domain;

use App\Manga\Domain\Volume;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'volume_entries')]
#[ORM\UniqueConstraint(columns: ['collection_entry_id', 'volume_id'])]
class VolumeEntry
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: CollectionEntry::class, inversedBy: 'volumeEntries')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public CollectionEntry $collectionEntry,
        #[ORM\ManyToOne(targetEntity: Volume::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Volume $volume,
        #[ORM\Column]
        public bool $isOwned = false,
        #[ORM\Column]
        public bool $isRead = false,
        #[ORM\Column]
        public bool $isWishlisted = false,
        #[ORM\Column(type: 'text', nullable: true)]
        public ?string $review = null,
        #[ORM\Column(nullable: true)]
        public ?int $rating = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'volumeId' => $this->volume->id,
            'number' => $this->volume->number,
            'coverUrl' => $this->volume->coverUrl,
            'priceCode' => $this->volume->priceCode?->toArray(),
            'isOwned' => $this->isOwned,
            'isRead' => $this->isRead,
            'isWishlisted' => $this->isWishlisted,
            'review' => $this->review,
            'rating' => $this->rating,
        ];
    }
}
