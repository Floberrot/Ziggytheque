<?php

declare(strict_types=1);

namespace App\Manga\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

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
        #[ORM\Column(length: 100)]
        public string $edition,
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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'edition' => $this->edition,
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
