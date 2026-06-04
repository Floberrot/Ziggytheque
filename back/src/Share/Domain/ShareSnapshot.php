<?php

declare(strict_types=1);

namespace App\Share\Domain;

use App\Auth\Domain\User;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * Immutable, public snapshot of a user's library stats captured at a moment in
 * time. Reachable by anyone holding the random token, it never changes and has
 * no expiry — the payload is frozen at creation.
 */
#[ORM\Entity]
#[ORM\Table(name: 'share_snapshots')]
#[ORM\UniqueConstraint(name: 'UNIQ_SHARE_SNAPSHOT_TOKEN', columns: ['token'])]
#[ORM\Index(name: 'IDX_SHARE_SNAPSHOT_OWNER', columns: ['owner_id'])]
class ShareSnapshot
{
    #[ORM\Column]
    public DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $payload the frozen public stats subset
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public readonly string $id,
        #[ORM\Column(length: 32)]
        public readonly string $token,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'owner_id', nullable: true, onDelete: 'CASCADE')]
        public ?User $owner,
        #[ORM\Column(length: 100)]
        public readonly string $ownerName,
        #[ORM\Column(type: 'json')]
        public readonly array $payload,
    ) {
        $this->createdAt = new DateTimeImmutable();
    }

    /**
     * Public, read-only representation served by the share endpoint.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'ownerName' => $this->ownerName,
            'createdAt' => $this->createdAt->format(DateTimeInterface::ATOM),
            'stats'     => $this->payload,
        ];
    }
}
