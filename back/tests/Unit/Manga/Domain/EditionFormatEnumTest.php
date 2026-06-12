<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\EditionFormatEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EditionFormatEnumTest extends TestCase
{
    #[DataProvider('provideRelieLabels')]
    public function testFromRawLabelReturnsRelie(string $label): void
    {
        $this->assertSame(EditionFormatEnum::Relie, EditionFormatEnum::fromRawLabel($label));
    }

    /** @return array<string, array{string}> */
    public static function provideRelieLabels(): array
    {
        return [
            'Hardcover'    => ['Hardcover'],
            'hardcover'    => ['hardcover'],
            'relié'        => ['relié'],
            'reliée'       => ['reliée'],
            'cartonné'     => ['cartonné'],
            'GRAND FORMAT' => ['GRAND FORMAT'],
        ];
    }

    #[DataProvider('provideDeluxeLabels')]
    public function testFromRawLabelReturnsDeluxe(string $label): void
    {
        $this->assertSame(EditionFormatEnum::Deluxe, EditionFormatEnum::fromRawLabel($label));
    }

    /** @return array<string, array{string}> */
    public static function provideDeluxeLabels(): array
    {
        return [
            'Deluxe'           => ['Deluxe'],
            'deluxe'           => ['deluxe'],
            'édition prestige' => ['édition prestige'],
            'édition Maximum'  => ['édition Maximum'],
            'Maximum'          => ['Maximum'],
        ];
    }

    #[DataProvider('provideOmnibusLabels')]
    public function testFromRawLabelReturnsOmnibus(string $label): void
    {
        $this->assertSame(EditionFormatEnum::Omnibus, EditionFormatEnum::fromRawLabel($label));
    }

    /** @return array<string, array{string}> */
    public static function provideOmnibusLabels(): array
    {
        return [
            'Omnibus'   => ['Omnibus'],
            '3-in-1'    => ['3-in-1'],
            '3 in 1'    => ['3 in 1'],
            'Intégrale' => ['Intégrale'],
        ];
    }

    #[DataProvider('provideCoffretLabels')]
    public function testFromRawLabelReturnsCoffret(string $label): void
    {
        $this->assertSame(EditionFormatEnum::Coffret, EditionFormatEnum::fromRawLabel($label));
    }

    /** @return array<string, array{string}> */
    public static function provideCoffretLabels(): array
    {
        return [
            'Coffret' => ['Coffret'],
            'coffret' => ['coffret'],
            'box set' => ['box set'],
        ];
    }

    public function testFromRawLabelReturnsUnknownForNull(): void
    {
        $this->assertSame(EditionFormatEnum::Unknown, EditionFormatEnum::fromRawLabel(null));
    }

    public function testFromRawLabelReturnsUnknownForEmptyString(): void
    {
        $this->assertSame(EditionFormatEnum::Unknown, EditionFormatEnum::fromRawLabel(''));
    }

    public function testFromRawLabelReturnsBrocheForGenericLabel(): void
    {
        $this->assertSame(EditionFormatEnum::Broche, EditionFormatEnum::fromRawLabel('Manga'));
    }

    public function testFromRawLabelReturnsBrocheForBroche(): void
    {
        $this->assertSame(EditionFormatEnum::Broche, EditionFormatEnum::fromRawLabel('broché'));
    }
}
