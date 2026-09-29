<?php

declare(strict_types=1);

namespace App\Manga\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'mangas')]
class Manga
{
    /** @var Collection<int, Volume> */
    #[ORM\OneToMany(
        targetEntity: Volume::class,
        mappedBy: 'manga',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['number' => 'ASC'])]
    public Collection $volumes;

    #[ORM\Column]
    public DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public readonly string $id,
        #[ORM\Column(length: 255)]
        public string $title,
        #[ORM\Column(length: 100, nullable: true)]
        public ?string $edition,
        #[ORM\Column(length: 10)]
        public string $language,
        #[ORM\Column(nullable: true)]
        public ?string $author = null,
        #[ORM\Column(type: 'text', nullable: true)]
        public ?string $summary = null,
        #[ORM\Column(nullable: true)]
        public ?string $coverUrl = null,
        #[ORM\Column(enumType: GenreEnum::class, nullable: true)]
        public ?GenreEnum $genre = null,
        #[ORM\Column(nullable: true)]
        public ?string $externalId = null,
        // Name of a special edition as the catalogue records it ("Prestige", "Perfect
        // edition", "Édition Hokage"…) — free text, never picked from a fixed list.
        // Null for the publisher's standard run.
        #[ORM\Column(length: 150, nullable: true)]
        public ?string $specialEdition = null,
    ) {
        $this->volumes = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    public function addVolume(Volume $volume): void
    {
        if (!$this->volumes->contains($volume)) {
            $this->volumes->add($volume);
        }
    }

    public function volumeByNumber(int $number): ?Volume
    {
        foreach ($this->volumes as $volume) {
            if ($volume->number === $number) {
                return $volume;
            }
        }

        return null;
    }

    /**
     * Creates the missing volume placeholders so that volumes 1..$lastNumber all
     * exist — adding tome 12 of a series known up to tome 10 also creates tome 11.
     *
     * @return list<Volume> the volumes created by this call
     */
    public function ensureVolumesUpTo(int $lastNumber): array
    {
        $created = [];
        for ($number = 1; $number <= $lastNumber; $number++) {
            if ($this->volumeByNumber($number) !== null) {
                continue;
            }

            $volume = new Volume(id: Uuid::v4()->toRfc4122(), manga: $this, number: $number);
            $this->addVolume($volume);
            $created[] = $volume;
        }

        return $created;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'edition' => $this->edition,
            'specialEdition' => $this->specialEdition,
            'language' => $this->language,
            'author' => $this->author,
            'summary' => $this->summary,
            'coverUrl' => $this->coverUrl,
            'genre' => $this->genre?->value,
            'externalId' => $this->externalId,
            'totalVolumes' => $this->volumes->count(),
            'createdAt' => $this->createdAt->format(DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function toDetailArray(): array
    {
        return array_merge($this->toArray(), [
            'volumes' => $this->volumes->map(fn (Volume $v) => $v->toArray())->toArray(),
        ]);
    }
}
