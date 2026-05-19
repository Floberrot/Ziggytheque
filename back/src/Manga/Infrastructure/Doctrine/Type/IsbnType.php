<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Doctrine\Type;

use App\Manga\Domain\Isbn;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

final class IsbnType extends StringType
{
    public const string NAME = 'isbn';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Isbn
    {
        if ($value === null || $value === '') {
            return null;
        }

        // tryFrom instead of fromString: corrupted DB data must not crash the app
        return Isbn::tryFrom((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof Isbn) {
            throw new ConversionException(
                sprintf('Could not convert value of type %s to %s.', get_debug_type($value), $this->getName())
            );
        }

        return $value->value;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /** @param array<string, mixed> $column */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] = $column['length'] ?? 20;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
