<?php

declare(strict_types=1);

namespace App\Manga\Domain;

/**
 * Accent-, case- and punctuation-insensitive form of a title, used to compare what a
 * catalogue returns with what is already stored ("L'Attaque des Titans" and
 * "l'attaque des titans" fold to the same key).
 */
final readonly class TextFold
{
    private const array ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ō' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
    ];

    public static function fold(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $lowered = strtr(mb_strtolower($value), self::ACCENTS);
        $spaced  = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $lowered);

        return trim($spaced);
    }
}
