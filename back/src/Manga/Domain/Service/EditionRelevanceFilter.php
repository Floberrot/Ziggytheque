<?php

declare(strict_types=1);

namespace App\Manga\Domain\Service;

/**
 * Keeps only catalogue records that belong to the searched work, without shrinking the
 * result to "known publishers only". Discovery must be exhaustive per country: classic
 * runs, prestige/perfect/deluxe lines, coffrets, intégrales, collectors AND companion
 * volumes a collector shelves next to the manga (artbooks, guidebooks, fanbooks).
 *
 * Relevance is therefore title-driven: a record is kept when its title actually matches
 * the searched work (every significant word of the work title appears in the record
 * title), whatever its publisher — unknown or missing publishers are no longer dropped.
 * The publisher allowlist survives only as a rescue signal for records whose title is a
 * translation of the work title (e.g. a Japanese edition surfacing under an English
 * work) and as a trust hint for ranking.
 *
 * What is still rejected as real noise for a manga médiathèque:
 * - video / sound carriers (dc:type),
 * - partwork, video and unofficial-essay publishers ({@see self::PUBLISHER_DENYLIST}),
 * - derivative print that is not a collectible edition: coloriages, agendas,
 *   calendriers, stickers, figurines, unofficial reading guides, novels, argus…
 */
final readonly class EditionRelevanceFilter
{
    /**
     * Recognised manga publishers, as folded (accent-free, lower-case) prefixes matched
     * against the normalised publisher key. No longer a hard gate: it rescues records
     * whose title does not literally match (translated titles) and marks trustworthy
     * sources. One token covers a house across markets (e.g. "panini" → Panini Manga
     * FR/DE/ES, Planet Manga IT).
     *
     * @var list<string>
     */
    private const array PUBLISHER_ALLOWLIST = [
        // France
        'glenat', 'kana', 'kurokawa', 'ki-oon', 'ki oon', 'pika', 'delcourt', 'tonkam',
        'soleil', 'kaze', 'doki', 'akata', 'nobi', 'black box', 'meian', 'taifu', 'imho',
        'casterman', 'sakka', 'naban', 'noeve', 'vega', 'h2t', 'bamboo', 'michel lafon',
        'kotoji', 'komikku', 'mangetsu', 'ankama', 'paquet', 'isan', 'crunchyroll',
        // Pan-European / multi-market
        'panini', 'planeta', 'norma', 'ivrea', 'star comics', 'j-pop', 'jpop', 'goen',
        'dynit', 'milky way', 'distrito manga', 'tokyopop', 'altraverse', 'manga cult',
        'carlsen', 'egmont', 'hayabusa',
        // North America
        'viz', 'dark horse', 'kodansha', 'seven seas', 'yen press', 'square enix',
        'vertical', 'udon', 'denpa', 'ghost ship', 'j-novel', 'one peace', 'digital manga',
        // Japan
        'shueisha', 'shogakukan', 'hakusensha', 'kadokawa', 'akita', 'futabasha',
        'houbunsha', 'ichijinsha', 'media factory', 'mag garden', 'takeshobo', 'enterbrain',
        'gentosha', 'libre', 'shinshokan', 'shonen gahosha', 'coamix', 'leed', 'flower comics',
    ];

    /**
     * Publishers whose output is never a manga edition: video arms sharing a manga
     * house's prefix ("Kazé Video" vs "Kazé Manga"), figurine/fascicle partworks, and
     * unofficial-essay houses. This stays a hard reject.
     *
     * @var list<string>
     */
    private const array PUBLISHER_DENYLIST = [
        'kana home video', 'kaze video', 'kaze anime', 'ab video', 'panini video',
        'deagostini', 'de agostini', 'altaya', 'hachette collections', 'atlas',
        'third editions', 'ynnis',
    ];

    /**
     * Folded (accent-free, lower-case) title fragments that mark non-collectible noise.
     * Artbooks / guidebooks / fanbooks are deliberately NOT here anymore — they are
     * legitimate editions surfaced with the Artbook format.
     */
    private const string TITLE_DENY_REGEX =
        '/\b(coloriages?|colou?ring|stickers?|autocollants?|calendriers?|calendars?'
        . '|agendas?|figurines?|puzzles?|dvd|blu[\s-]?ray|making\s+of|decryptages?'
        . '|cote\s+des|argus|novels?|romans?)\b'
        . '|guide\s+de\s+lecture|non\s+officiel|unofficial|encyclop/iu';

    /** Record types (Dublin Core dc:type and friends) that are not printed books. */
    private const string TYPE_DENY_REGEX =
        '/\b(vid(?:é|e)o|son|sound|dvd|blu[\s-]?ray|movie|film)\b|image\s+anim/iu';

    /**
     * Words too generic to prove a title match on their own — dropped before requiring
     * every remaining work-title word to appear in the record title.
     *
     * @var list<string>
     */
    private const array TITLE_STOPWORDS = [
        'the', 'an', 'of', 'and', 'on', 'in', 'at', 'to',
        'le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et',
        'no', 'na', 'wa', 'ni', 'ga', 'die', 'der', 'das', 'und',
    ];

    public function __construct(private PublisherNormalizer $publisherNormalizer)
    {
    }

    public function isRelevant(
        string $workTitle,
        string $recordTitle,
        ?string $publisher,
        ?string $type = null,
    ): bool {
        if ($type !== null && $type !== '' && preg_match(self::TYPE_DENY_REGEX, $type) === 1) {
            return false;
        }

        $publisherKey = $this->publisherNormalizer->canonicalKey($publisher);
        foreach (self::PUBLISHER_DENYLIST as $denied) {
            if ($publisherKey !== '' && str_starts_with($publisherKey, $denied)) {
                return false;
            }
        }

        if (preg_match(self::TITLE_DENY_REGEX, $this->fold($recordTitle)) === 1) {
            return false;
        }

        // A title that matches the work is enough — the publisher may be unknown, tiny
        // or missing (artbooks, coffrets and one-shot collectors often are).
        if ($this->titleMatchesWork($workTitle, $recordTitle)) {
            return true;
        }

        // Translated / renamed record titles: trust a recognised manga house.
        return $this->isKnownMangaPublisher($publisherKey);
    }

    /**
     * True when every significant word of the work title appears in the record title
     * (position-independent, accent/case-insensitive). Whole-string containment covers
     * CJK titles, which have no word boundaries.
     */
    private function titleMatchesWork(string $workTitle, string $recordTitle): bool
    {
        $foldedWork   = $this->fold($workTitle);
        $foldedRecord = $this->fold($recordTitle);

        if ($foldedWork === '' || $foldedRecord === '') {
            return false;
        }

        if (str_contains($foldedRecord, $foldedWork)) {
            return true;
        }

        $significantWords = $this->significantWords($foldedWork);
        if ($significantWords === []) {
            return false;
        }

        foreach ($significantWords as $word) {
            if (!str_contains($foldedRecord, $word)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function significantWords(string $foldedTitle): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $foldedTitle, -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false) {
            return [];
        }

        $significant = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < 2 || in_array($word, self::TITLE_STOPWORDS, true)) {
                continue;
            }
            $significant[] = $word;
        }

        return $significant;
    }

    private function isKnownMangaPublisher(string $publisherKey): bool
    {
        if ($publisherKey === '') {
            return false;
        }

        foreach (self::PUBLISHER_ALLOWLIST as $known) {
            if (str_starts_with($publisherKey, $known)) {
                return true;
            }
        }

        return false;
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
