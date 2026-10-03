<?php

declare(strict_types=1);

namespace App\Collection\Domain;

use App\Auth\Domain\User;
use App\Manga\Domain\Manga;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'collection_entries')]
#[ORM\UniqueConstraint(name: 'UNIQ_79D4C1147E3C61F97B6461', columns: ['owner_id', 'manga_id'])]
class CollectionEntry
{
    /** @var Collection<int, VolumeEntry> */
    #[ORM\OneToMany(
        targetEntity: VolumeEntry::class,
        mappedBy: 'collectionEntry',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    public Collection $volumeEntries;

    #[ORM\Column]
    public DateTimeImmutable $addedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Manga::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Manga $manga,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'owner_id', nullable: true, onDelete: 'CASCADE')]
        public ?User $owner = null,
        #[ORM\Column(enumType: ReadingStatusEnum::class)]
        public ReadingStatusEnum $readingStatus = ReadingStatusEnum::NotStarted,
        #[ORM\Column(type: 'text', nullable: true)]
        public ?string $review = null,
        #[ORM\Column(nullable: true)]
        public ?int $rating = null,
        #[ORM\Column(options: ['default' => false])]
        public bool $notificationsEnabled = false,
        #[ORM\Column(nullable: true)]
        public ?DateTimeImmutable $notificationsEnabledAt = null,
    ) {
        $this->volumeEntries = new ArrayCollection();
        $this->addedAt       = new DateTimeImmutable();
    }

    /**
     * The series is this account's own copy: removing the entry removes it too, with
     * its tomes, covers and prices.
     */
    public function ownsItsSeries(): bool
    {
        return $this->owner !== null && $this->manga->isOwnedBy($this->owner->id);
    }

    /**
     * Starts tracking every volume of the series that has no entry yet (volumes added
     * to the series after the user started collecting it).
     *
     * @return int number of volume entries created
     */
    public function trackMissingVolumes(): int
    {
        $trackedVolumeIds = [];
        foreach ($this->volumeEntries as $volumeEntry) {
            $trackedVolumeIds[$volumeEntry->volume->id] = true;
        }

        $createdCount = 0;
        foreach ($this->manga->volumes as $volume) {
            if (isset($trackedVolumeIds[$volume->id])) {
                continue;
            }

            $this->volumeEntries->add(new VolumeEntry(
                id: Uuid::v4()->toRfc4122(),
                collectionEntry: $this,
                volume: $volume,
            ));
            $createdCount++;
        }

        return $createdCount;
    }

    public function volumeEntryForNumber(int $number): ?VolumeEntry
    {
        foreach ($this->volumeEntries as $volumeEntry) {
            if ($volumeEntry->volume->number === $number) {
                return $volumeEntry;
            }
        }

        return null;
    }

    /**
     * Derives the reading status from the volumes, except when the user parked or
     * dropped the series on purpose.
     */
    public function refreshReadingStatus(): void
    {
        if (in_array($this->readingStatus, [ReadingStatusEnum::Dropped, ReadingStatusEnum::OnHold], true)) {
            return;
        }

        $total = $this->volumeEntries->count();
        if ($total === 0) {
            return;
        }

        $ownedCount = $this->volumeEntries->filter(fn (VolumeEntry $volumeEntry) => $volumeEntry->isOwned)->count();
        $readCount  = $this->volumeEntries->filter(fn (VolumeEntry $volumeEntry) => $volumeEntry->isRead)->count();

        $this->readingStatus = match (true) {
            $readCount === $total              => ReadingStatusEnum::Completed,
            $readCount > 0 || $ownedCount > 0 => ReadingStatusEnum::InProgress,
            default                            => ReadingStatusEnum::NotStarted,
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'                     => $this->id,
            'manga'                  => $this->manga->toArray(),
            'readingStatus'          => $this->readingStatus->value,
            'review'                 => $this->review,
            'rating'                 => $this->rating,
            'ownedCount'             => $this->volumeEntries
                ->filter(fn (VolumeEntry $volumeEntry) => $volumeEntry->isOwned)
                ->count(),
            'readCount'              => $this->volumeEntries
                ->filter(fn (VolumeEntry $volumeEntry) => $volumeEntry->isRead)
                ->count(),
            'wishedCount'            => $this->volumeEntries
                ->filter(fn (VolumeEntry $volumeEntry) => $volumeEntry->isWished && !$volumeEntry->isOwned)
                ->count(),
            'totalVolumes'           => $this->manga->volumes->count(),
            'notificationsEnabled'   => $this->notificationsEnabled,
            'notificationsEnabledAt' => $this->notificationsEnabledAt?->format(DateTimeInterface::ATOM),
            'addedAt'                => $this->addedAt->format(DateTimeInterface::ATOM),
            'ownedValue'             => (float) array_sum(array_map(
                fn (VolumeEntry $volumeEntry) => $volumeEntry->isOwned ? ($volumeEntry->volume->price ?? 0.0) : 0.0,
                $this->volumeEntries->toArray(),
            )),
        ];
    }

    /** @return array<string, mixed> */
    public function toDetailArray(): array
    {
        $volumes = $this->volumeEntries->toArray();
        usort(
            $volumes,
            static fn (VolumeEntry $volumeEntryA, VolumeEntry $volumeEntryB) => $volumeEntryA->volume->number
                <=> $volumeEntryB->volume->number,
        );

        return array_merge($this->toArray(), [
            'volumes' => array_map(static fn (VolumeEntry $volumeEntry) => $volumeEntry->toArray(), $volumes),
        ]);
    }
}
