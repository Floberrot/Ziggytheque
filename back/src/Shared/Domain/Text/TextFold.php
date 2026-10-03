<?php

declare(strict_types=1);

namespace App\Shared\Domain\Text;

/**
 * The one place text is folded for comparison: titles, publishers, merchants and news
 * articles must agree on what "the same words" means ("L'Attaque des Titans" and
 * "l'attaque des titans", "Glénat" and "GLENAT").
 *
 * The accent table is explicit rather than iconv//TRANSLIT, whose output depends on
 * the server locale.
 */
final readonly class TextFold
{
    private const array ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ō' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'ç' => 'c', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
    ];

    /**
     * Case-, accent- and punctuation-insensitive key: every run of characters that are
     * neither letters nor digits becomes one space ("Kaiju n°8 !" → "kaiju n 8").
     */
    public static function fold(?string $value): string
    {
        $spaced = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', self::foldAccents($value));

        return trim($spaced);
    }

    /**
     * Lower-cased and accent-free, punctuation kept ("Ki-oon", "Amazon.fr" stay
     * hyphenated / dotted): for keys matched against lists written with punctuation.
     */
    public static function foldAccents(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return strtr(mb_strtolower(trim($value)), self::ACCENTS);
    }
}
