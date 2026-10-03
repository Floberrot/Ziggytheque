<?php

declare(strict_types=1);

namespace App\Manga\Domain;

use App\Manga\Domain\Service\PublisherNormalizer;
use App\Shared\Domain\Text\TextFold;

/**
 * What makes two records the same series in a collector's eyes: the work, the
 * publisher (imprint) and the special edition. "Berserk — Glénat" and "Berserk —
 * Glénat · Prestige" are two series; "Glénat (Grenoble)" and "Glénat" are one, and so
 * are "Prestige", "Édition prestige" and "Éd. prestige".
 */
final readonly class EditionIdentity
{
    /** The word "édition" (or its abbreviation) said around the edition's own name. */
    private const string EDITION_WORD = '/^(?:edition|ed)\s+|\s+(?:edition|ed)$/u';

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
            self::specialEditionKey($this->specialEdition),
        ]);
    }

    private static function specialEditionKey(?string $specialEdition): string
    {
        $folded = TextFold::fold($specialEdition);
        $named  = (string) preg_replace(self::EDITION_WORD, '', $folded);

        return $named === '' ? $folded : $named;
    }

    public function matches(self $other, PublisherNormalizer $publisherNormalizer): bool
    {
        return $this->key($publisherNormalizer) === $other->key($publisherNormalizer);
    }
}
