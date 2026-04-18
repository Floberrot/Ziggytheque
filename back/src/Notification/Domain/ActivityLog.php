<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use App\Collection\Domain\CollectionEntry;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_logs')]
class ActivityLog
{
    #[ORM\Column]
    public \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $finishedAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public readonly string $id,

        #[ORM\ManyToOne(targetEntity: CollectionEntry::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public readonly CollectionEntry $collectionEntry,

        /** 'rss' | 'jikan' */
        #[ORM\Column(length: 20)]
        public readonly string $sourceType,

        #[ORM\Column(length: 100)]
        public readonly string $sourceName,

        /** 'running' | 'success' | 'error' */
        #[ORM\Column(length: 20)]
        public string $status = 'running',

        #[ORM\Column(type: 'text', nullable: true)]
        public ?string $errorMessage = null,

        #[ORM\Column(nullable: true)]
        public ?int $newArticlesCount = null,
    ) {
        $this->startedAt = new \DateTimeImmutable();
    }

    public function markSuccess(int $newArticlesCount): void
    {
        $this->status           = 'success';
        $this->newArticlesCount = $newArticlesCount;
        $this->finishedAt       = new \DateTimeImmutable();
    }

    public function markError(string $message): void
    {
        $this->status       = 'error';
        $this->errorMessage = $message;
        $this->finishedAt   = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'collectionEntryId' => $this->collectionEntry->id,
            'mangaTitle'        => $this->collectionEntry->manga->title,
            'sourceType'        => $this->sourceType,
            'sourceName'        => $this->sourceName,
            'status'            => $this->status,
            'errorMessage'      => $this->errorMessage,
            'newArticlesCount'  => $this->newArticlesCount,
            'startedAt'         => $this->startedAt->format(\DateTimeInterface::ATOM),
            'finishedAt'        => $this->finishedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
