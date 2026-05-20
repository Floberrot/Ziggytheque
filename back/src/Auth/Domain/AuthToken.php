<?php

declare(strict_types=1);

namespace App\Auth\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'auth_tokens')]
class AuthToken
{
    #[ORM\Column]
    public DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?DateTimeImmutable $consumedAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
        public readonly User $user,
        #[ORM\Column(enumType: AuthTokenTypeEnum::class)]
        public readonly AuthTokenTypeEnum $type,
        #[ORM\Column(length: 64)]
        public readonly string $tokenHash,
        #[ORM\Column]
        public readonly DateTimeImmutable $expiresAt,
    ) {
        $this->createdAt = new DateTimeImmutable();
    }

    public function isValid(): bool
    {
        return $this->consumedAt === null && $this->expiresAt > new DateTimeImmutable();
    }

    public function consume(): void
    {
        $this->consumedAt = new DateTimeImmutable();
    }
}
