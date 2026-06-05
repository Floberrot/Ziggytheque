<?php

declare(strict_types=1);

namespace App\Manga\Domain;

enum Marketplace: string
{
    case Fr = 'EBAY_FR';
    case Us = 'EBAY_US';

    public function ebayId(): string
    {
        return $this->value;
    }

    public function currencyCode(): string
    {
        return match ($this) {
            self::Fr => 'EUR',
            self::Us => 'USD',
        };
    }

    public static function fromLanguage(?string $language): self
    {
        return match ($language) {
            'en' => self::Us,
            default => self::Fr,
        };
    }

    public static function fromValue(?string $raw): self
    {
        if ($raw === null) {
            return self::Fr;
        }

        return self::tryFrom($raw) ?? self::Fr;
    }
}
