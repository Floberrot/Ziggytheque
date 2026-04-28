<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use App\Collection\Domain\CollectionEntry;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'articles')]
#[ORM\UniqueConstraint(name: 'uniq_article_entry_url', columns: ['collection_entry_id', 'url'])]
class Article
{
    #[ORM\Column]
    public DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: CollectionEntry::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public readonly CollectionEntry $collectionEntry,
        #[ORM\Column(length: 500)]
        public readonly string $title,
        #[ORM\Column(type: 'text')]
        public readonly string $url,
        #[ORM\Column(length: 100)]
        public readonly string $sourceName,
        #[ORM\Column(length: 255, nullable: true)]
        public readonly ?string $author,
        #[ORM\Column(type: 'text', nullable: true)]
        public readonly ?string $imageUrl,
        #[ORM\Column(nullable: true)]
        public readonly ?DateTimeImmutable $publishedAt,
        #[ORM\Column(type: 'text', nullable: true)]
        public readonly ?string $snippet = null,
    ) {
        $this->createdAt = new DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'collectionEntry' => [
                'id'    => $this->collectionEntry->id,
                'manga' => [
                    'id'       => $this->collectionEntry->manga->id,
                    'title'    => $this->collectionEntry->manga->title,
                    'coverUrl' => $this->collectionEntry->manga->coverUrl,
                ],
            ],
            'title'       => $this->title,
            'url'         => $this->url,
            'sourceName'  => $this->sourceName,
            'author'      => $this->author,
            'imageUrl'    => $this->imageUrl,
            'snippet'     => $this->snippet,
            'publishedAt' => $this->publishedAt?->format(DateTimeInterface::ATOM),
            'createdAt'   => $this->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
