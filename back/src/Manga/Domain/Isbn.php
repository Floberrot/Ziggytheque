<?php

declare(strict_types=1);

namespace App\Manga\Domain;

use App\Manga\Domain\Exception\InvalidIsbnException;

final readonly class Isbn
{
    /** @param string $value 13-digit canonical form without hyphens or spaces */
    private function __construct(public string $value)
    {
    }

    /** @throws InvalidIsbnException */
    public static function fromString(string $raw): self
    {
        $normalized = self::normalize($raw);
        $length = strlen($normalized);

        return match ($length) {
            13 => self::fromIsbn13($normalized),
            10 => self::fromIsbn13(self::convertIsbn10ToIsbn13($normalized)),
            default => throw new InvalidIsbnException(
                sprintf('ISBN length must be 10 or 13 digits after normalization, got %d for "%s"', $length, $raw),
            ),
        };
    }

    public static function tryFrom(?string $raw): ?self
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        try {
            return self::fromString($raw);
        } catch (InvalidIsbnException) {
            return null;
        }
    }

    public function prefix(): string
    {
        return substr($this->value, 0, 3);
    }

    public function groupIdentifier(): string
    {
        // Simplified: returns the first digit after the 978/979 prefix (single-digit group only)
        return substr($this->value, 3, 1);
    }

    public function withHyphens(): string
    {
        // Format as 978-G-PPPPP-AAA-C (simple 3-1-5-3-1 split for display)
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($this->value, 0, 3),
            substr($this->value, 3, 1),
            substr($this->value, 4, 5),
            substr($this->value, 9, 3),
            substr($this->value, 12, 1),
        );
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function normalize(string $raw): string
    {
        // Strip hyphens, spaces, and convert X to uppercase
        $stripped = str_replace(['-', ' '], '', strtoupper(trim($raw)));

        // Only digits and trailing X (for ISBN-10) are valid
        if (!preg_match('/^[0-9]+[X]?$|^[0-9]+$/', $stripped)) {
            throw new InvalidIsbnException(
                sprintf('ISBN contains invalid characters: "%s"', $raw),
            );
        }

        return $stripped;
    }

    /** @throws InvalidIsbnException */
    private static function fromIsbn13(string $digits): self
    {
        if (!str_starts_with($digits, '978') && !str_starts_with($digits, '979')) {
            throw new InvalidIsbnException(
                sprintf('ISBN-13 must start with 978 or 979, got "%s"', substr($digits, 0, 3)),
            );
        }

        self::validateIsbn13Checksum($digits);

        return new self($digits);
    }

    /** @throws InvalidIsbnException */
    private static function convertIsbn10ToIsbn13(string $isbn10): string
    {
        // ISBN-10: validate checksum before converting
        self::validateIsbn10Checksum($isbn10);

        // Prepend 978 + first 9 digits of ISBN-10 + compute new checksum
        $first12 = '978' . substr($isbn10, 0, 9);

        return $first12 . self::isbn13Checksum($first12);
    }

    /** @throws InvalidIsbnException */
    private static function validateIsbn13Checksum(string $isbn13): void
    {
        $sum = 0;
        for ($position = 0; $position < 12; $position++) {
            $digit = (int) $isbn13[$position];
            $sum += ($position % 2 === 0) ? $digit : $digit * 3;
        }

        $expectedCheckDigit = (10 - ($sum % 10)) % 10;
        $actualCheckDigit = (int) $isbn13[12];

        if ($expectedCheckDigit !== $actualCheckDigit) {
            throw new InvalidIsbnException(
                sprintf(
                    'Invalid ISBN-13 checksum: expected %d, got %d for "%s"',
                    $expectedCheckDigit,
                    $actualCheckDigit,
                    $isbn13,
                ),
            );
        }
    }

    /** @throws InvalidIsbnException */
    private static function validateIsbn10Checksum(string $isbn10): void
    {
        $sum = 0;
        for ($position = 0; $position < 9; $position++) {
            $sum += (int) $isbn10[$position] * (10 - $position);
        }

        $lastChar = $isbn10[9];
        $lastValue = $lastChar === 'X' ? 10 : (int) $lastChar;
        $sum += $lastValue;

        if ($sum % 11 !== 0) {
            throw new InvalidIsbnException(
                sprintf('Invalid ISBN-10 checksum for "%s"', $isbn10),
            );
        }
    }

    private static function isbn13Checksum(string $first12): string
    {
        $sum = 0;
        for ($position = 0; $position < 12; $position++) {
            $digit = (int) $first12[$position];
            $sum += ($position % 2 === 0) ? $digit : $digit * 3;
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }
}
