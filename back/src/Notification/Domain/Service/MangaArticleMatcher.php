<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

use App\Shared\Domain\Text\TextFold;

/**
 * Decides whether a news article actually mentions a followed manga.
 *
 * A followed title often carries a trailing edition/variant descriptor
 * (e.g. "Dorohedoro - Chaos edition", "Berserk - Édition collector"). Matching
 * on individual words such as "edition" produced false positives: an article
 * about a different series ("La Complete Edition du manga Hunt - Beast Side")
 * was attached to "Dorohedoro" only because both contained the word "edition".
 *
 * The rule enforced here: the core series title — the edition descriptor
 * stripped off — must appear as a whole, word-bounded phrase in the article
 * text. The work must absolutely be named; a loose keyword overlap is not enough.
 * Both sides are folded ({@see TextFold::fold}), so the phrase match ignores case,
 * accents and punctuation.
 */
final readonly class MangaArticleMatcher
{
    /**
     * Words marking the trailing title segment as an edition/variant descriptor
     * rather than part of the work's name. Compared diacritics-folded, so the
     * accented forms ("édition", "intégrale") are covered by the ASCII entries.
     *
     * @var list<string>
     */
    private const EDITION_MARKERS = [
        'edition',
        'editions',
        'collector',
        'deluxe',
        'integrale',
        'coffret',
        'ultimate',
        'kanzenban',
        'perfect',
        'anniversary',
        'hardcover',
        'omnibus',
    ];

    /**
     * True when the core series title appears, as a whole word-bounded phrase,
     * anywhere in the supplied article text (title + description/excerpt).
     */
    public function mentions(string $mangaTitle, string $articleText): bool
    {
        $core = TextFold::fold($this->coreTitle($mangaTitle));
        if ($core === '') {
            return false;
        }

        $haystack = TextFold::fold($articleText);
        if ($haystack === '') {
            return false;
        }

        return str_contains(' ' . $haystack . ' ', ' ' . $core . ' ');
    }

    /**
     * The folded significant words of the core title, in order. Callers use
     * them to build a relevant snippet around the first occurrence in the text.
     *
     * @return list<string>
     */
    public function coreTitleWords(string $mangaTitle): array
    {
        $core = TextFold::fold($this->coreTitle($mangaTitle));

        return $core === '' ? [] : explode(' ', $core);
    }

    /**
     * Strips a trailing edition/variant descriptor segment from the title.
     * "Dorohedoro - Chaos edition" → "Dorohedoro"; "Hunt - Beast Side" is kept
     * intact because no segment looks like an edition descriptor.
     */
    private function coreTitle(string $mangaTitle): string
    {
        $segments = preg_split('/\s+[-–—]\s+|\s*:\s+/u', trim($mangaTitle)) ?: [$mangaTitle];

        while (count($segments) > 1 && $this->isEditionDescriptor((string) end($segments))) {
            array_pop($segments);
        }

        return implode(' ', $segments);
    }

    private function isEditionDescriptor(string $segment): bool
    {
        $normalized = TextFold::fold($segment);
        if ($normalized === '') {
            return false;
        }

        foreach (explode(' ', $normalized) as $word) {
            if (in_array($word, self::EDITION_MARKERS, true)) {
                return true;
            }
        }

        return false;
    }
}
