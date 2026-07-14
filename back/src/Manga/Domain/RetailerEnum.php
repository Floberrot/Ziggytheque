<?php

declare(strict_types=1);

namespace App\Manga\Domain;

/**
 * The target shops the price screen always reports on — found or honestly "not found".
 * Merchant names scraped or returned by APIs are mapped onto these via
 * {@see self::fromMerchant} (case- and accent-insensitive prefix match).
 */
enum RetailerEnum: string
{
    case Amazon = 'amazon';
    case Fnac   = 'fnac';
    case Ebay   = 'ebay';

    /** @return list<self> */
    public static function targets(): array
    {
        return [self::Amazon, self::Fnac, self::Ebay];
    }

    /** Maps a raw merchant name ("Amazon.fr", "la Fnac", "eBay FR"…) onto a target retailer. */
    public static function fromMerchant(string $merchant): ?self
    {
        $normalized = self::normalizeMerchant($merchant);

        foreach (self::cases() as $retailer) {
            if (str_starts_with($normalized, $retailer->value)) {
                return $retailer;
            }
        }

        return null;
    }

    public function label(): string
    {
        return match ($this) {
            self::Amazon => 'Amazon',
            self::Fnac   => 'Fnac',
            self::Ebay   => 'eBay',
        };
    }

    /** Deterministic accent folding (iconv//TRANSLIT is locale-dependent, so not used). */
    private const array ACCENT_MAP = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ÿ' => 'y', 'ñ' => 'n',
    ];

    private static function normalizeMerchant(string $merchant): string
    {
        $folded = strtr(mb_strtolower(trim($merchant)), self::ACCENT_MAP);

        // "La Fnac", "the amazon store"… — drop leading articles so the prefix match holds.
        return (string) preg_replace('/^(la|le|les|the)\s+/', '', $folded);
    }
}
