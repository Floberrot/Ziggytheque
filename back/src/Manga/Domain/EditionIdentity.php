<?php

declare(strict_types=1);

namespace App\Manga\Domain;

use App\Manga\Domain\Service\PublisherNormalizer;

/**
 * What makes two records the same series in a collector's eyes: the work, the
 * publisher (imprint) and the special edition. "Berserk — Glénat" and "Berserk —
 * Glénat · Prestige" are two series; "Glénat (Grenoble)" and "Glénat" are one.
 */
final readonly class EditionIdentity
{
    public function __construct(
        public string $workTitle,
        public ?string $publisher,
        public ?string $specialEdition,
    ) {
    }

    public static function ofManga(Manga $manga): self
    {
        return new self($manga->title, $manga->edition, $manga->specialEdition);
    }

    public function key(PublisherNormalizer $publisherNormalizer): string
    {
        return implode('|', [
            TextFold::fold($this->workTitle),
            $publisherNormalizer->imprintKey($this->publisher),
            TextFold::fold($this->specialEdition),
        ]);
    }

    public function matches(self $other, PublisherNormalizer $publisherNormalizer): bool
    {
        return $this->key($publisherNormalizer) === $other->key($publisherNormalizer);
    }
}
