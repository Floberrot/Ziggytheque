<?php

declare(strict_types=1);

namespace App\Manga\Domain\Service;

/**
 * Detects the "edition line" of a catalogue record — the curated marketing name a
 * collector recognises (Perfect Edition, Édition originale, Coffret, Deluxe…) — by
 * scanning the raw record title / subtitle for known statements.
 *
 * This is what distinguishes "Glénat — Perfect Edition" from "Glénat — Édition
 * originale": both share publisher + language, only the line differs. Returns a
 * canonical, display-ready label, or null for a plain numbered volume.
 */
final readonly class EditionLineExtractor
{
    /**
     * Ordered patterns (most specific first) mapped to their canonical label.
     * The first match wins, so "édition de luxe" resolves to Deluxe before the
     * looser "édition" fragments can interfere.
     *
     * @var array<string, string>
     */
    private const array PATTERNS = [
        // Japanese edition markers (NDL records are titled in kanji).
        '/完全版/u'                                                           => 'Perfect Edition',
        '/愛蔵版/u'                                                           => 'Deluxe',
        '/新装版/u'                                                           => 'Nouvelle édition',
        '/文庫/u'                                                             => 'Bunko',
        '/カラー版/u'                                                          => 'Édition couleur',
        '/総集編/u'                                                           => 'Intégrale',
        '/perfect\s*edition/iu'                                              => 'Perfect Edition',
        '/kanzenban/iu'                                                      => 'Perfect Edition',
        '/(?:é|e)dition\s+originale|sens\s+de\s+lecture\s+original/iu'       => 'Édition originale',
        '/(?:é|e)dition\s+double|\bdouble\s+edition\b|\bdouble\b(?=.*tome)/iu' => 'Édition double',
        '/\bprestige\b/iu'                                                    => 'Prestige',
        '/\bmaximum\b/iu'                                                     => 'Maximum',
        '/\bultimate\b/iu'                                                    => 'Ultimate',
        '/\bcollector\b/iu'                                                   => 'Collector',
        '/(?:é|e)dition\s+de\s+luxe|\bdeluxe\b|de\s+luxe/iu'                  => 'Deluxe',
        '/\bcoffret\b|box\s*set|\bbox\b/iu'                                   => 'Coffret',
        '/(?:é|e)dition\s+couleur|\bcolou?r\s+edition\b|\ben\s+couleur\b/iu'  => 'Édition couleur',
        '/\bint(?:é|e)grale\b|\bomnibus\b|\d\s*[-]?in[-]?\s*\d|\bvizbig\b/iu' => 'Intégrale',
    ];

    /**
     * Descriptors that follow "édition"/"edition" but denote a language, a binding or
     * the plain run rather than a collectible edition line — never surfaced as a line.
     *
     * @var list<string>
     */
    private const array EXCLUDED_DESCRIPTORS = [
        'de', 'du', 'la', 'le', 'l', 'en', 'et', 'des', 'originale',
        'française', 'francaise', 'anglaise', 'japonaise', 'américaine', 'americaine',
        'allemande', 'espagnole', 'italienne', 'numérique', 'numerique',
        'standard', 'courante', 'normale', 'simple', 'brochée', 'brochee',
        'reliée', 'reliee', 'souple', 'rigide', 'grand', 'format', 'poche',
        'complète', 'complete',
        'first', 'new', 'original', 'revised', 'reprint',
    ];

    /**
     * @param string ...$fragments title, subtitle, edition statement — any free text
     */
    public function extract(string ...$fragments): ?string
    {
        $haystack = trim(implode(' ', array_filter($fragments, static fn (string $part): bool => $part !== '')));

        if ($haystack === '') {
            return null;
        }

        foreach (self::PATTERNS as $pattern => $label) {
            if (preg_match($pattern, $haystack) === 1) {
                return $label;
            }
        }

        return $this->genericStatement($haystack);
    }

    /**
     * Catches the long tail of named editions (pastel, anniversaire, limitée, spéciale…)
     * without enumerating every marketing name, by reading the word after "édition" —
     * or before "edition" — and skipping language / binding descriptors.
     */
    private function genericStatement(string $haystack): ?string
    {
        if (preg_match('/(?:é|e)dition\s+([\p{L}]+)/iu', $haystack, $matches) === 1) {
            $descriptor = mb_strtolower($matches[1]);
            if (!in_array($descriptor, self::EXCLUDED_DESCRIPTORS, true)) {
                return 'Édition ' . $descriptor;
            }
        }

        if (preg_match('/\b([\p{L}]+)\s+edition\b/iu', $haystack, $matches) === 1) {
            $descriptor = mb_strtolower($matches[1]);
            if (!in_array($descriptor, self::EXCLUDED_DESCRIPTORS, true)) {
                return ucfirst($descriptor) . ' Edition';
            }
        }

        return null;
    }
}
