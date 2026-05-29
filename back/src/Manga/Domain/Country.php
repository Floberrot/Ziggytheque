<?php

declare(strict_types=1);

namespace App\Manga\Domain;

/**
 * Country whose book market editions are searched. The chosen country drives
 * both the query language and which discovery providers are relevant (the BnF,
 * for instance, only catalogs works deposited in France).
 */
enum Country: string
{
    case France = 'FR';
    case UnitedStates = 'US';
    case Japan = 'JP';

    /**
     * ISO 639-1 language code used when querying external edition sources.
     */
    public function language(): string
    {
        return match ($this) {
            self::France => 'fr',
            self::UnitedStates => 'en',
            self::Japan => 'ja',
        };
    }

    public static function default(): self
    {
        return self::France;
    }

    /**
     * Resolves a country from an arbitrary code, falling back to the default
     * when the code is null, empty or unknown.
     */
    public static function fromCode(?string $code): self
    {
        if ($code === null || trim($code) === '') {
            return self::default();
        }

        return self::tryFrom(strtoupper(trim($code))) ?? self::default();
    }
}
