<?php

declare(strict_types=1);

namespace App\Manga\Domain;

enum EditionFormatEnum: string
{
    case Broche = 'broche';
    case Relie = 'relie';
    case Coffret = 'coffret';
    case Deluxe = 'deluxe';
    case Omnibus = 'omnibus';
    case Unknown = 'unknown';

    public static function fromRawLabel(?string $raw): self
    {
        if ($raw === null) {
            return self::Unknown;
        }

        $normalized = mb_strtolower($raw);

        if (preg_match('/omnibus|int[eé]grale|3.?in.?1|3-en-1|mook/iu', $normalized)) {
            return self::Omnibus;
        }

        if (preg_match('/coffret|box(?:\s|$)/iu', $normalized)) {
            return self::Coffret;
        }

        if (preg_match('/deluxe|prestige|maximum|édition de luxe|luxury/iu', $normalized)) {
            return self::Deluxe;
        }

        if (preg_match('/hardcover|relié|reli[ée]|cartonn[ée]|hard\s*cover|grand format|hc\b/iu', $normalized)) {
            return self::Relie;
        }

        if (preg_match('/paperback|broché|broch[ée]|poche|softcover|sc\b/iu', $normalized)) {
            return self::Broche;
        }

        if ($normalized === '') {
            return self::Unknown;
        }

        return self::Broche;
    }
}
