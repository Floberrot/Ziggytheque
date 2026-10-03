<?php

declare(strict_types=1);

namespace App\Manga\Domain\Service;

use App\Manga\Domain\Catalogue\ParsedTitle;
use App\Shared\Domain\Text\TextFold;

/**
 * Splits a catalogue title into work, edition qualifier and volume number by reading
 * its STRUCTURE — never by matching a list of known edition names. Publishers name
 * their special editions however they like ("Prestige", "Perfect edition", "Édition
 * Hokage", "Black edition"…); whatever sits in the edition slot is kept verbatim.
 *
 * Structures handled:
 * - BnF (ISBD):      "Berserk : prestige. 12, L'épée du chevalier noir / Kentaro Miura",
 *                    "Berserk. 5 (Éd. prestige)"
 * - Google Books:    "One Piece - Édition originale - Tome 12", "Berserk T12", "Berserk - 5",
 *                    "Berserk - Tome 1 - Édition prestige" (qualifier after the number)
 *
 * The head qualifier (between the work and the number) is edition-level by structure.
 * The trailing part (after the number) may be a volume title or an edition name: an
 * explicit edition statement ("Éd. prestige", "Perfect edition") is promoted to the
 * head; anything else is left to the assembler, which checks whether it repeats.
 */
final readonly class CatalogueTitleParser
{
    /** BnF ISBD: the volume is the segment after the last ". " — "Dr. Stone. 3" stays intact. */
    private const string ISBD_VOLUME = '/^(?<head>.+?)\.\s+(?<number>\d{1,4})(?<rest>(?:\s*[,:;\-–—(].*)?)$/u';

    /** A volume marker word left at the end of an ISBD head ("… Vol" of "… Vol. 1"). */
    private const string TRAILING_MARKER = '/[\s.,:;\-–—]*\b(?:tome|volume|vol|t|n°|no)\.?$/iu';

    /**
     * Explicit volume markers, strongest first. The first marker kind found wins, so
     * "Kaiju n°8 - Tome 3" resolves to tome 3 instead of the "n°8" of the title.
     *
     * @var list<string>
     */
    private const array VOLUME_MARKERS = [
        '/^(?<head>.*?)[\s\-–—,:.]*\b(?:tome|volume|vol\.?)\s*(?<number>\d{1,4})\b(?<rest>.*)$/iu',
        '/^(?<head>.*?)[\s\-–—,:.]*\bt\.?\s?(?<number>\d{1,4})\b(?<rest>.*)$/iu',
        // A bare number after a spaced separator: "Berserk - 5 (Éd. prestige)", "Kaiju n°8 - 3".
        '/^(?<head>.+?)\s+[:\-–—]\s+(?<number>\d{1,4})(?<rest>(?:\s*[,:;\-–—(].*)?)$/u',
        '/^(?<head>.*?)[\s\-–—,:.]*(?:\bn°|\bno\.|#)\s*(?<number>\d{1,4})\b(?<rest>.*)$/iu',
    ];

    /** A parenthesised note closing the work title: "Berserk (édition prestige)". */
    private const string PARENTHESISED_TAIL = '/^(?<work>.+?)\s*\((?<note>[^()]+)\)$/u';

    /**
     * "Éd." abbreviation of "édition", as catalogues print it ("Éd. prestige"). At the
     * end of an ISBD head the dot is the volume separator: "Nausicaä : nouvelle éd. 1".
     */
    private const string EDITION_ABBREVIATION = '/(?<!\p{L})([ÉéEe])d(?:\.(?=[\s),;:]|$)|(?=[\s),;:.]*$))/u';

    /** Folded first or last word that marks a statement as naming an edition. */
    private const array EDITION_WORDS = ['ed', 'edition'];

    /** Separators between a work title and its edition qualifier, spaced on both sides. */
    private const string QUALIFIER_SEPARATOR = '/\s+[:\-–—]\s+/u';

    /**
     * Qualifiers that say nothing about the edition (language, format, genre noise).
     * Folded form. This is a stoplist of generic words, not a list of edition names.
     *
     * @var list<string>
     */
    private const array GENERIC_QUALIFIERS = [
        'manga', 'mangas', 'bd', 'comics', 'roman', 'vf', 'version francaise',
        'edition francaise', 'french edition', 'francais', 'texte imprime', 'tome', 'volume',
    ];

    /** Matches the mangas.special_edition column. */
    private const int MAX_QUALIFIER_LENGTH = 150;

    /** Matches the mangas.title column. */
    private const int MAX_WORK_TITLE_LENGTH = 255;

    public function parse(string $title, ?string $subtitle = null): ParsedTitle
    {
        $text = $this->clean($title);
        $cleanedSubtitle = $this->clean($subtitle ?? '');
        if ($cleanedSubtitle !== '') {
            $text .= ' - ' . $cleanedSubtitle;
        }

        [$head, $number, $rest] = $this->splitVolume($text);
        [$workTitle, $rawHeadQualifier] = $this->splitQualifier($head);

        $headQualifier     = $this->qualifier($rawHeadQualifier);
        $trailingQualifier = $number !== null ? $this->qualifier($rest) : null;

        // "Berserk - Tome 5 - Édition prestige": the trailing part names the edition itself.
        if ($headQualifier === null && $trailingQualifier !== null && $this->isEditionStatement($trailingQualifier)) {
            [$headQualifier, $trailingQualifier] = [$trailingQualifier, null];
        }

        return new ParsedTitle(
            workTitle: mb_substr($workTitle, 0, self::MAX_WORK_TITLE_LENGTH),
            headQualifier: $headQualifier,
            volumeNumber: $number,
            trailingQualifier: $trailingQualifier,
        );
    }

    /**
     * A search typed by the user: "berserk prestige 5" → text "berserk prestige" and
     * the tome the user is after (5). Only a bare trailing number counts.
     *
     * @return array{text: string, volumeNumber: int|null}
     */
    public function parseQuery(string $query): array
    {
        $trimmed = trim((string) preg_replace('/\s+/u', ' ', $query));
        $parsed  = $this->parse($trimmed);

        if ($parsed->volumeNumber !== null) {
            $text = trim($parsed->workTitle . ' ' . ($parsed->headQualifier ?? ''));

            return ['text' => $text, 'volumeNumber' => $parsed->volumeNumber];
        }

        if (preg_match('/^(?<text>.*\D)\s+(?<number>\d{1,4})$/u', $trimmed, $matches) === 1) {
            return ['text' => trim($matches['text']), 'volumeNumber' => (int) $matches['number']];
        }

        return ['text' => $trimmed, 'volumeNumber' => null];
    }

    /** Drops the responsibility statement ("/ Kentaro Miura") and bracketed notes. */
    private function clean(string $value): string
    {
        $withoutAuthors = (string) preg_replace('#\s+/\s+.*$#u', '', $value);
        $withoutNotes   = (string) preg_replace('/\[[^\]]*\]/u', '', $withoutAuthors);

        return trim((string) preg_replace('/\s+/u', ' ', $withoutNotes));
    }

    /** @return array{0: string, 1: int|null, 2: string} head, volume number, rest */
    private function splitVolume(string $text): array
    {
        if (preg_match(self::ISBD_VOLUME, $text, $matches) === 1) {
            // "Berserk : édition deluxe. Vol. 1" — the marker word sits before the dot.
            $head = (string) preg_replace(self::TRAILING_MARKER, '', $matches['head']);

            return [$head, (int) $matches['number'], $matches['rest']];
        }

        foreach (self::VOLUME_MARKERS as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1 && trim($matches['head']) !== '') {
                return [$matches['head'], (int) $matches['number'], $matches['rest']];
            }
        }

        return [$text, null, ''];
    }

    /** @return array{0: string, 1: string} work title, qualifier ('' when none) */
    private function splitQualifier(string $head): array
    {
        $trimmedHead = $this->trimSeparators($head);
        $parts = preg_split(self::QUALIFIER_SEPARATOR, $trimmedHead, 2);

        if ($parts !== false && count($parts) === 2) {
            return [$this->trimSeparators($parts[0]), $parts[1]];
        }

        if (
            preg_match(self::PARENTHESISED_TAIL, $trimmedHead, $matches) === 1
            && $this->isEditionStatement($matches['note'])
        ) {
            return [$this->trimSeparators($matches['work']), $matches['note']];
        }

        return [$trimmedHead, ''];
    }

    private function qualifier(string $raw): ?string
    {
        // Expanded first: trimming would eat the dot of a closing "Nouvelle éd.".
        $expanded = (string) preg_replace_callback(
            self::EDITION_ABBREVIATION,
            static fn (array $matches): string => in_array($matches[1], ['É', 'E'], true) ? 'Édition' : 'édition',
            $raw,
        );
        $trimmed = $this->trimSeparators($this->unwrapParentheses($this->trimSeparators($expanded)));
        if ($trimmed === '' || in_array(TextFold::fold($trimmed), self::GENERIC_QUALIFIERS, true)) {
            return null;
        }

        $capitalised = mb_strtoupper(mb_substr($trimmed, 0, 1)) . mb_substr($trimmed, 1);

        return mb_substr($capitalised, 0, self::MAX_QUALIFIER_LENGTH);
    }

    /** "Éd. prestige", "Édition Hokage", "Perfect edition" — a statement naming an edition. */
    private function isEditionStatement(string $statement): bool
    {
        $words = explode(' ', TextFold::fold($statement));

        return count($words) >= 2
            && (in_array($words[0], self::EDITION_WORDS, true) || in_array(end($words), self::EDITION_WORDS, true));
    }

    /** "(Éd. prestige)" → "Éd. prestige"; parentheses that do not wrap everything stay. */
    private function unwrapParentheses(string $value): string
    {
        if (preg_match('/^\((?<inner>[^()]*)\)$/u', $value, $matches) === 1) {
            return $matches['inner'];
        }

        return $value;
    }

    private function trimSeparators(string $value): string
    {
        return (string) preg_replace('/^[\s.,:;\-–—]+|[\s.,:;\-–—]+$/u', '', $value);
    }
}
