<?php

declare(strict_types=1);

namespace App\Collection\Domain;

use App\Manga\Domain\Manga;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'collection_entries')]
#[ORM\UniqueConstraint(columns: ['manga_id'])]
class CollectionEntry
{
    #[ORM\OneToMany(targetEntity: VolumeEntry::class, mappedBy: 'collectionEntry', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $volumeEntries;

    #[ORM\Column]
    public \DateTimeImmutable $addedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Manga::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Manga $manga,
        #[ORM\Column(enumType: ReadingStatusEnum::class)]
        public ReadingStatusEnum $readingStatus = ReadingStatusEnum::NotStarted,
        #[ORM\Column(type: 'text', nullable: true)]
        public ?string $review = null,
        #[ORM\Column(nullable: true)]
        public ?int $rating = null,
    ) {
        $this->volumeEntries = new ArrayCollection();
        $this->addedAt = new \DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'manga' => $this->manga->toArray(),
            'readingStatus' => $this->readingStatus->value,
            'review' => $this->review,
            'rating' => $this->rating,
            'ownedCount' => $this->volumeEntries->filter(fn (VolumeEntry $ve) => $ve->isOwned)->count(),
            'readCount' => $this->volumeEntries->filter(fn (VolumeEntry $ve) => $ve->isRead)->count(),
            'wishedCount' => $this->volumeEntries->filter(fn (VolumeEntry $ve) => $ve->isWished && !$ve->isOwned)->count(),
            'totalVolumes' => $this->manga->volumes->count(),
            'addedAt' => $this->addedAt->format(\DateTimeInterface::ATOM),
            'ownedValue' => array_sum(array_map(
                fn (VolumeEntry $ve) => $ve->isOwned ? ($ve->volume->price ?? 0.0) : 0.0,
                $this->volumeEntries->toArray(),
            )),
        ];
    }

    public function toDetailArray(): array
    {
        $volumes = $this->volumeEntries->toArray();
        usort($volumes, static fn (VolumeEntry $a, VolumeEntry $b) => $a->volume->number <=> $b->volume->number);

        return array_merge($this->toArray(), [
            'volumes' => array_map(static fn (VolumeEntry $ve) => $ve->toArray(), $volumes),
        ]);
    }
}
