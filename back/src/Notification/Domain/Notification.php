<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
class Notification
{
    #[ORM\Column]
    public DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public readonly string $id,
        #[ORM\Column(length: 50)]
        public string $type,
        #[ORM\Column(type: 'text')]
        public string $message,
        #[ORM\Column]
        public bool $isRead = false,
    ) {
        $this->createdAt = new DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'type'      => $this->type,
            'message'   => $this->message,
            'isRead'    => $this->isRead,
            'createdAt' => $this->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
