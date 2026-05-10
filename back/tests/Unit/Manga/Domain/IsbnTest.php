<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\Exception\InvalidIsbnException;
use App\Manga\Domain\Isbn;
use PHPUnit\Framework\TestCase;

final class IsbnTest extends TestCase
{
    // Valid ISBN-13: 9782123456780 — checksum verified:
    //   sum(d_i * weight_i, 0..11) = 120, check = (10 - 0) % 10 = 0
    private const string VALID_ISBN13 = '9782123456780';

    // Valid ISBN-13 with prefix 979: 9791034701360 — checksum verified
    private const string VALID_ISBN13_979 = '9791034701360';

    // Valid ISBN-10: 2123456802 — sum 165, 165%11=0
    private const string VALID_ISBN10 = '2123456802';

    // Valid ISBN-10 with X check digit: 048665088X — sum 253, 253%11=0
    private const string VALID_ISBN10_X = '048665088X';

    // --- Valid ISBN-13 construction ---

    public function testFromStringAcceptsValidIsbn13(): void
    {
        $isbn = Isbn::fromString(self::VALID_ISBN13);

        $this->assertSame(self::VALID_ISBN13, $isbn->value);
    }

    public function testFromStringStripsHyphens(): void
    {
        $isbn = Isbn::fromString('978-2-1234-5678-0');

        $this->assertSame(self::VALID_ISBN13, $isbn->value);
    }

    public function testFromStringStripsSpaces(): void
    {
        $isbn = Isbn::fromString(' 978 2 1234 5678 0 ');

        $this->assertSame(self::VALID_ISBN13, $isbn->value);
    }

    public function testFromStringAccepts979Prefix(): void
    {
        $isbn = Isbn::fromString(self::VALID_ISBN13_979);

        $this->assertSame('979', $isbn->prefix());
    }

    // --- Valid ISBN-10 converted to ISBN-13 ---

    public function testFromStringConvertsIsbn10ToIsbn13(): void
    {
        $isbn = Isbn::fromString(self::VALID_ISBN10);

        $this->assertSame('978', $isbn->prefix());
        $this->assertSame(13, strlen($isbn->value));
    }

    public function testFromStringAcceptsIsbn10WithXCheckDigit(): void
    {
        $isbn = Isbn::fromString(self::VALID_ISBN10_X);

        $this->assertSame(13, strlen($isbn->value));
        $this->assertSame('978', $isbn->prefix());
    }

    // --- Invalid ISBN-13 ---

    public function testFromStringRejectsInvalidPrefix(): void
    {
        $this->expectException(InvalidIsbnException::class);
        // Prefix "123" is not 978 or 979
        Isbn::fromString('1234567890123');
    }

    public function testFromStringRejectsWrongIsbn13Checksum(): void
    {
        $this->expectException(InvalidIsbnException::class);
        // 9782123456789 — last digit should be 0, not 9
        Isbn::fromString('9782123456789');
    }

    // --- Invalid ISBN-10 ---

    public function testFromStringRejectsWrongIsbn10Checksum(): void
    {
        $this->expectException(InvalidIsbnException::class);
        // 2123456800 — last digit should be 2, not 0
        Isbn::fromString('2123456800');
    }

    // --- Invalid lengths ---

    public function testFromStringRejectsLength11(): void
    {
        $this->expectException(InvalidIsbnException::class);
        Isbn::fromString('12345678901');
    }

    public function testFromStringRejectsLength12(): void
    {
        $this->expectException(InvalidIsbnException::class);
        Isbn::fromString('978212345678');
    }

    public function testFromStringRejectsLength14(): void
    {
        $this->expectException(InvalidIsbnException::class);
        Isbn::fromString('97821234567890');
    }

    // --- Invalid characters ---

    public function testFromStringRejectsNonNumericCharacter(): void
    {
        $this->expectException(InvalidIsbnException::class);
        Isbn::fromString('978A123456789');
    }

    public function testFromStringRejectsEmptyString(): void
    {
        $this->expectException(InvalidIsbnException::class);
        Isbn::fromString('');
    }

    // --- tryFrom ---

    public function testTryFromReturnsNullForNull(): void
    {
        $this->assertNull(Isbn::tryFrom(null));
    }

    public function testTryFromReturnsNullForEmptyString(): void
    {
        $this->assertNull(Isbn::tryFrom(''));
    }

    public function testTryFromReturnsNullForWhitespaceOnly(): void
    {
        $this->assertNull(Isbn::tryFrom('  '));
    }

    public function testTryFromReturnsNullForInvalidIsbn(): void
    {
        $this->assertNull(Isbn::tryFrom('invalid'));
    }

    public function testTryFromReturnsInstanceForValidIsbn(): void
    {
        $isbn = Isbn::tryFrom(self::VALID_ISBN13);

        $this->assertInstanceOf(Isbn::class, $isbn);
        $this->assertSame(self::VALID_ISBN13, $isbn->value);
    }

    // --- Public API ---

    public function testPrefixReturns978(): void
    {
        $isbn = Isbn::fromString(self::VALID_ISBN13);

        $this->assertSame('978', $isbn->prefix());
    }

    public function testGroupIdentifierReturnsFrenchGroup(): void
    {
        // 9782... → group identifier is "2" (francophonie)
        $isbn = Isbn::fromString(self::VALID_ISBN13);

        $this->assertSame('2', $isbn->groupIdentifier());
    }

    public function testWithHyphensFormatsCorrectly(): void
    {
        $isbn = Isbn::fromString(self::VALID_ISBN13);
        $formatted = $isbn->withHyphens();

        $this->assertStringContainsString('-', $formatted);
        // Removing hyphens must give back the canonical 13-digit value
        $this->assertSame($isbn->value, str_replace('-', '', $formatted));
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $isbn1 = Isbn::fromString(self::VALID_ISBN13);
        $isbn2 = Isbn::fromString('978-2-1234-5678-0');

        $this->assertTrue($isbn1->equals($isbn2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $isbn1 = Isbn::fromString(self::VALID_ISBN13);
        $isbn2 = Isbn::fromString(self::VALID_ISBN13_979);

        $this->assertFalse($isbn1->equals($isbn2));
    }

    public function testToStringReturnsCanonicalValue(): void
    {
        $isbn = Isbn::fromString(self::VALID_ISBN13);

        $this->assertSame(self::VALID_ISBN13, (string) $isbn);
    }
}
