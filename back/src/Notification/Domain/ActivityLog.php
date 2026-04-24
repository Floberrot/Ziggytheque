<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use App\Collection\Domain\CollectionEntry;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_logs')]
class ActivityLog
{
    #[ORM\Column]
    public DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    public ?DateTimeImmutable $finishedAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public readonly string $id,
        #[ORM\Column(enumType: EventTypeEnum::class, length: 20)]
        public readonly EventTypeEnum $eventType,
        #[ORM\Column(length: 100)]
        public readonly string $sourceName,
        #[ORM\ManyToOne(targetEntity: CollectionEntry::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        public readonly ?CollectionEntry $collectionEntry = null,
        /** 'running' | 'success' | 'error' */
        #[ORM\Column(length: 20)]
        public string $status = 'running',
        #[ORM\Column(type: 'text', nullable: true)]
        public ?string $errorMessage = null,
        #[ORM\Column(nullable: true)]
        public ?int $newArticlesCount = null,
        /** @var array<string, mixed> */
        #[ORM\Column(type: 'json', nullable: true)]
        public ?array $metadata = null,
    ) {
        $this->startedAt = new \DateTimeImmutable();
    }

    /** @param array<string, mixed> $metadata */
    public function markSuccess(int $newArticlesCount = 0, array $metadata = []): void
    {
        $this->status           = 'success';
        $this->newArticlesCount = $newArticlesCount;
        $this->finishedAt       = new DateTimeImmutable();
        if ($metadata !== []) {
            $this->metadata = array_merge($this->metadata ?? [], $metadata);
        }
    }

    /** @param array<string, mixed> $metadata */
    public function markError(string $message, array $metadata = []): void
    {
        $this->status       = 'error';
        $this->errorMessage = mb_substr($message, 0, 2000);
        $this->finishedAt   = new DateTimeImmutable();
        if ($metadata !== []) {
            $this->metadata = array_merge($this->metadata ?? [], $metadata);
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'eventType'        => $this->eventType->value,
            'sourceName'       => $this->sourceName,
            'collectionEntryId' => $this->collectionEntry?->id,
            'mangaTitle'       => $this->collectionEntry?->manga->title,
            'status'           => $this->status,
            'errorMessage'     => $this->errorMessage,
            'newArticlesCount' => $this->newArticlesCount,
            'metadata'         => $this->metadata,
            'startedAt'        => $this->startedAt->format(DateTimeInterface::ATOM),
            'finishedAt'       => $this->finishedAt?->format(DateTimeInterface::ATOM),
            'durationMs'       => $this->finishedAt !== null
                ? (int) (($this->finishedAt->format('U.u') - $this->startedAt->format('U.u')) * 1000)
                : null,
        ];
    }
}
