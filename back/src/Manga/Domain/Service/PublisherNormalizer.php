<?php

declare(strict_types=1);

namespace App\Manga\Domain\Service;

/**
 * Turns the noisy publisher strings catalogues return — with cities, legal suffixes
 * and historical aliases — into a single canonical name, so the same publisher does
 * not appear five times.
 *
 * Catalogues report "Glénat (Grenoble)", "Comics USA (Grenoble)" /
 * "Comics-USA (Grenoble)", and Viz as "Viz", "Viz Media", "VIZ Media LLC",
 * "Viz Communications", "Shonen Jump Graphic Novel/Viz". All of those must collapse.
 */
final readonly class PublisherNormalizer
{
    /**
     * Substring (accent-folded, lower-cased) → canonical display name. The first
     * matching alias wins, so historical / imprint names fold into the modern label.
     *
     * @var array<string, string>
     */
    private const array ALIASES = [
        'viz'              => 'Viz Media',
        'shonen jump'      => 'Viz Media',
        'comics usa'       => 'Comics USA',
        'comics-usa'       => 'Comics USA',
        'glenat'           => 'Glénat',
        'dark horse'       => 'Dark Horse',
        'kurokawa'         => 'Kurokawa',
        'ki-oon'           => 'Ki-oon',
        'ki oon'           => 'Ki-oon',
        'kana'             => 'Kana',
        'pika'             => 'Pika',
        'tonkam'           => 'Delcourt/Tonkam',
        'delcourt'         => 'Delcourt/Tonkam',
        'hakusensha'       => 'Hakusensha',
        'shueisha'         => 'Shueisha',
        'kadokawa'         => 'Kadokawa',
        // Japanese houses returned in kanji by JP catalogues — map to romaji so they
        // match the publisher allowlist and display with a readable name.
        '集英社'            => 'Shueisha',
        '小学館'            => 'Shogakukan',
        '講談社'            => 'Kodansha',
        '白泉社'            => 'Hakusensha',
        '角川'             => 'Kadokawa',
        '秋田書店'          => 'Akita Shoten',
        '双葉社'            => 'Futabasha',
        '一迅社'            => 'Ichijinsha',
        '芳文社'            => 'Houbunsha',
        'スクウェア'         => 'Square Enix',
    ];

    /** Legal / corporate suffixes stripped from the tail of a publisher name. */
    private const array LEGAL_SUFFIXES = [
        'llc', 'inc', 'ltd', 'sa', 'sarl', 'gmbh', 'co', 'publishing', 'publications', 'media',
    ];

    /** Human-readable, deduplicated publisher name (null when blank). */
    public function displayName(?string $raw): ?string
    {
        $cleaned = $this->clean($raw ?? '');
        if ($cleaned === '') {
            return null;
        }

        $folded = $this->fold($cleaned);
        foreach (self::ALIASES as $needle => $canonical) {
            if (str_contains($folded, $needle)) {
                return $canonical;
            }
        }

        return $this->stripLegalSuffix($cleaned);
    }

    /** Stable, accent/case-insensitive key used to group editions of one publisher. */
    public function canonicalKey(?string $raw): string
    {
        $display = $this->displayName($raw);

        return $display === null ? '' : $this->fold($display);
    }

    /** Strips parenthetical/bracketed city suffixes and "Éd./Éditions" prefixes. */
    private function clean(string $raw): string
    {
        $value = trim($raw);

        // Drop trailing "(City)" / "[éd.]" groups, including several in a row
        // (e.g. "Panorama [éd.] (Saint-Denis-la-Plaine)").
        $value = (string) preg_replace('/(?:\s*[\(\[][^)\]]*[\)\]])+\s*$/u', '', $value);
        $value = trim($value);

        // Drop a leading "Éd. " / "Ed. " / "Éditions " / "Editions " prefix.
        $value = (string) preg_replace('/^(?:é|e)d(?:itions)?\.?\s+/iu', '', $value);

        return trim((string) preg_replace('/\s{2,}/u', ' ', $value));
    }

    private function stripLegalSuffix(string $value): string
    {
        $words = explode(' ', $value);

        while (count($words) > 1) {
            $tail = rtrim(mb_strtolower(end($words)), '.');
            if (!in_array($tail, self::LEGAL_SUFFIXES, true)) {
                break;
            }
            array_pop($words);
        }

        return implode(' ', $words);
    }

    private function fold(string $value): string
    {
        $lower = mb_strtolower(trim($value));

        return strtr($lower, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
    }
}
