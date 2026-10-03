<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Text;

use App\Shared\Domain\Text\TextFold;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TextFoldTest extends TestCase
{
    public function testFoldsCaseAccentsAndPunctuation(): void
    {
        $this->assertSame('l attaque des titans', TextFold::fold("L'Attaque   des Titans !"));
        $this->assertSame('edition coeur', TextFold::fold('Édition Cœur'));
        $this->assertSame('kaiju n 8', TextFold::fold('Kaiju n°8'));
    }

    public function testFoldsNullAndBlankToAnEmptyString(): void
    {
        $this->assertSame('', TextFold::fold(null));
        $this->assertSame('', TextFold::fold(' - '));
        $this->assertSame('', TextFold::foldAccents(null));
        $this->assertSame('', TextFold::foldAccents('   '));
    }

    /** @return iterable<string, array{string, string}> */
    public static function accentedLetters(): iterable
    {
        yield 'grave and circumflex' => ['À LA FNÂC', 'a la fnac'];
        yield 'acute and diaeresis'  => ['Éditions Kazé', 'editions kaze'];
        yield 'macron (romaji)'      => ['Shōnen Jūmp', 'shonen jump'];
        yield 'ligatures'            => ['Œuvre Æther Straße', 'oeuvre aether strasse'];
        yield 'y and n'              => ['Ÿ ý Ñ', 'y y n'];
    }

    #[DataProvider('accentedLetters')]
    public function testFoldAccentsStripsEveryAccentInTheTable(string $raw, string $expected): void
    {
        $this->assertSame($expected, TextFold::foldAccents($raw));
    }

    public function testFoldAccentsKeepsPunctuationButTrimsSpaces(): void
    {
        $this->assertSame('ki-oon', TextFold::foldAccents('  Ki-Oon '));
        $this->assertSame('amazon.fr', TextFold::foldAccents('Amazon.fr'));
        $this->assertSame('delcourt/tonkam', TextFold::foldAccents('Delcourt/Tonkam'));
    }

    public function testFoldIsFoldAccentsWithPunctuationCollapsed(): void
    {
        $this->assertSame('ki oon', TextFold::fold('Ki-Oon'));
        $this->assertSame(TextFold::fold('Glénat (Grenoble)'), TextFold::fold('GLENAT  grenoble'));
    }

    public function testNonLatinScriptsAreKeptAsTheyAre(): void
    {
        $this->assertSame('集英社', TextFold::fold('集英社'));
        $this->assertSame('進撃の巨人 1', TextFold::fold('進撃の巨人 1'));
    }
}
