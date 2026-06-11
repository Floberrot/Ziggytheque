<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

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

    /** @var array<string, string> */
    private const DIACRITIC_MAP = [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
        'ç' => 'c',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o',
        'û' => 'u', 'ü' => 'u', 'ù' => 'u', 'ú' => 'u',
        'ÿ' => 'y', 'ý' => 'y',
        'ñ' => 'n',
        'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
    ];

    /**
     * True when the core series title appears, as a whole word-bounded phrase,
     * anywhere in the supplied article text (title + description/excerpt).
     */
    public function mentions(string $mangaTitle, string $articleText): bool
    {
        $core = $this->normalize($this->coreTitle($mangaTitle));
        if ($core === '') {
            return false;
        }

        $haystack = $this->normalize($articleText);
        if ($haystack === '') {
            return false;
        }

        return str_contains(' ' . $haystack . ' ', ' ' . $core . ' ');
    }

    /**
     * The normalized significant words of the core title, in order. Callers use
     * them to build a relevant snippet around the first occurrence in the text.
     *
     * @return list<string>
     */
    public function coreTitleWords(string $mangaTitle): array
    {
        $core = $this->normalize($this->coreTitle($mangaTitle));

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
        $normalized = $this->normalize($segment);
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

    /**
     * Lowercase, fold common diacritics, collapse every run of non-alphanumeric
     * characters to a single space, then trim. The result is a space-delimited
     * token stream so phrase matching is punctuation- and accent-insensitive.
     */
    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, self::DIACRITIC_MAP);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';

        return trim($value);
    }
}
