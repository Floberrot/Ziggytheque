<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure\Doctrine\Type;

use App\Manga\Domain\Isbn;
use App\Manga\Infrastructure\Doctrine\Type\IsbnType;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;

final class IsbnTypeTest extends TestCase
{
    private IsbnType $type;
    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new IsbnType();
        $this->platform = new PostgreSQLPlatform();
    }

    public function testConvertToPHPValueReturnsIsbnForValidString(): void
    {
        $isbn = $this->type->convertToPHPValue('9782123456780', $this->platform);

        $this->assertInstanceOf(Isbn::class, $isbn);
        $this->assertSame('9782123456780', $isbn->value);
    }

    public function testConvertToPHPValueReturnsNullForNull(): void
    {
        $result = $this->type->convertToPHPValue(null, $this->platform);

        $this->assertNull($result);
    }

    public function testConvertToPHPValueReturnsNullForEmptyString(): void
    {
        $result = $this->type->convertToPHPValue('', $this->platform);

        $this->assertNull($result);
    }

    public function testConvertToPHPValueReturnsNullForCorruptedData(): void
    {
        // Corrupted DB data must not crash — tryFrom returns null
        $result = $this->type->convertToPHPValue('not-an-isbn', $this->platform);

        $this->assertNull($result);
    }

    public function testConvertToDatabaseValueReturnsStringForIsbn(): void
    {
        $isbn = Isbn::fromString('9782123456780');
        $result = $this->type->convertToDatabaseValue($isbn, $this->platform);

        $this->assertSame('9782123456780', $result);
    }

    public function testConvertToDatabaseValueReturnsNullForNull(): void
    {
        $result = $this->type->convertToDatabaseValue(null, $this->platform);

        $this->assertNull($result);
    }

    public function testConvertToDatabaseValueThrowsForInvalidType(): void
    {
        $this->expectException(ConversionException::class);
        $this->type->convertToDatabaseValue('not-an-isbn-object', $this->platform);
    }

    public function testGetNameReturnsIsbn(): void
    {
        $this->assertSame('isbn', $this->type->getName());
    }

    public function testRoundTripIsbnThroughType(): void
    {
        $original = Isbn::fromString('9782123456780');
        $stored = $this->type->convertToDatabaseValue($original, $this->platform);
        $retrieved = $this->type->convertToPHPValue($stored, $this->platform);

        $this->assertInstanceOf(Isbn::class, $retrieved);
        $this->assertTrue($original->equals($retrieved));
    }
}
