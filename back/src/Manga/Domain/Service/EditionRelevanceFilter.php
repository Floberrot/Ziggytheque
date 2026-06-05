<?php

declare(strict_types=1);

namespace App\Manga\Domain\Service;

/**
 * Keeps only catalogue records that are an actual manga edition, by requiring a
 * recognised manga publisher. A broad title search drags in video releases, figurine
 * partworks, guides, artbooks and novels — denylisting that endless tail never ends,
 * so instead we ALLOWLIST the real manga publishers per market and drop everything
 * else. Curated on purpose: extend {@see self::PUBLISHER_ALLOWLIST} for a new house.
 *
 * A record is kept only when its publisher is a known manga house AND it is not a
 * video/sound carrier AND its title is not a derivative work (guide, artbook, novel…).
 */
final readonly class EditionRelevanceFilter
{
    /**
     * Recognised manga publishers, as folded (accent-free, lower-case) prefixes matched
     * against the normalised publisher key. One token covers a house across markets
     * (e.g. "panini" → Panini Manga FR/DE/ES, Planet Manga IT).
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
     * Video / partwork arms that share a manga house's name prefix and would otherwise
     * sneak past the allowlist (e.g. "Kazé Video" vs "Kazé Manga").
     *
     * @var list<string>
     */
    private const array PUBLISHER_DENYLIST = [
        'kana home video', 'kaze video', 'kaze anime', 'ab video', 'panini video',
        'deagostini', 'de agostini', 'altaya',
    ];

    /** Folded (accent-free, lower-case) title fragments that mark derivative / non-manga print. */
    private const string TITLE_DENY_REGEX =
        '/\b(guides?|art\s?books?|art\s+of|data\s?books?|fan\s?books?|anime\s+comics?'
        . '|novels?|encyclop|making\s+of|decryptage|cote\s+des|argus|dvd|blu[\s-]?ray'
        . '|coloriages?|stickers?|autocollants?|calendriers?|agendas?|figurines?|artworks?)\b'
        . '|the\s+world\s+of|l\'?univers\s+d/iu';

    /** Record types (Dublin Core dc:type and friends) that are not printed books. */
    private const string TYPE_DENY_REGEX =
        '/\b(vid(?:é|e)o|son|sound|dvd|blu[\s-]?ray|movie|film)\b|image\s+anim/iu';

    public function __construct(private PublisherNormalizer $publisherNormalizer)
    {
    }

    public function isRelevant(string $title, ?string $publisher, ?string $type = null): bool
    {
        $publisherKey = $this->publisherNormalizer->canonicalKey($publisher);
        if ($publisherKey === '') {
            return false;
        }

        foreach (self::PUBLISHER_DENYLIST as $denied) {
            if (str_starts_with($publisherKey, $denied)) {
                return false;
            }
        }

        if ($type !== null && $type !== '' && preg_match(self::TYPE_DENY_REGEX, $type) === 1) {
            return false;
        }

        if (preg_match(self::TITLE_DENY_REGEX, $this->fold($title)) === 1) {
            return false;
        }

        return $this->isKnownMangaPublisher($publisherKey);
    }

    private function isKnownMangaPublisher(string $publisherKey): bool
    {
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
