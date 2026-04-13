<?php

declare(strict_types=1);

namespace App\Manga\Domain;

use App\PriceCode\Domain\PriceCode;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'volumes')]
#[ORM\UniqueConstraint(columns: ['manga_id', 'number'])]
class Volume
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Manga::class, inversedBy: 'volumes')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Manga $manga,
        #[ORM\Column]
        public int $number,
        #[ORM\Column(nullable: true)]
        public ?string $coverUrl = null,
        #[ORM\ManyToOne(targetEntity: PriceCode::class)]
        #[ORM\JoinColumn(name: 'price_code', referencedColumnName: 'code', nullable: true, onDelete: 'SET NULL')]
        public ?PriceCode $priceCode = null,
        #[ORM\Column(nullable: true)]
        public ?\DateTimeImmutable $releaseDate = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'coverUrl' => $this->coverUrl,
            'priceCode' => $this->priceCode?->toArray(),
            'releaseDate' => $this->releaseDate?->format(\DateTimeInterface::ATOM),
        ];
    }
}
