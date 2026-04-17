<?php

declare(strict_types=1);

namespace App\PriceCode\Domain;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'price_codes')]
class PriceCode
{
    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 20)]
        public readonly string $code,
        #[ORM\Column(length: 100)]
        public string $label,
        #[ORM\Column(type: 'float')]
        public float $value,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function update(string $label, float $value): void
    {
        $this->label = $label;
        $this->value = $value;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'value' => $this->value,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
