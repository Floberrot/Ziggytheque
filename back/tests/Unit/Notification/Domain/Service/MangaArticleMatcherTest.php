<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain\Service;

use App\Notification\Domain\Service\MangaArticleMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MangaArticleMatcherTest extends TestCase
{
    private MangaArticleMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new MangaArticleMatcher();
    }

    /**
     * The reported bug: an article about a different series was attached to
     * "Dorohedoro - Chaos edition" only because both contained "edition".
     */
    public function testRejectsArticleThatDoesNotNameTheWork(): void
    {
        $this->assertFalse($this->matcher->mentions(
            'Dorohedoro - Chaos edition',
            'La Complete Edition du manga Hunt - Beast Side se précise',
        ));
    }

    public function testMatchesWhenTheCoreTitleIsNamed(): void
    {
        $this->assertTrue($this->matcher->mentions(
            'Dorohedoro - Chaos edition',
            'Dorohedoro : la Chaos Edition repoussée au printemps 2027',
        ));
    }

    public function testMatchesAgainstTheDescriptionNotOnlyTheTitle(): void
    {
        $this->assertTrue($this->matcher->mentions(
            'Berserk',
            'Nouveautés du mois — au programme, un nouveau tome de Berserk attendu',
        ));
    }

    #[DataProvider('editionDescriptorTitles')]
    public function testStripsTrailingEditionDescriptor(string $title, string $expectedCore): void
    {
        // The bare series name is enough to match…
        $this->assertTrue($this->matcher->mentions($title, $expectedCore . ' : annonce officielle'));
        // …while a generic edition headline naming another work is not.
        $this->assertFalse($this->matcher->mentions(
            $title,
            'La nouvelle édition collector deluxe du manga One Piece arrive',
        ));
    }

    /** @return iterable<string, array{string, string}> */
    public static function editionDescriptorTitles(): iterable
    {
        yield 'chaos edition'      => ['Dorohedoro - Chaos edition', 'Dorohedoro'];
        yield 'accented edition'   => ['Berserk - Édition collector', 'Berserk'];
        yield 'colon separator'    => ['Vinland Saga : Édition originale', 'Vinland Saga'];
        yield 'deluxe alone'       => ['Gunnm - Deluxe', 'Gunnm'];
        yield 'integrale accented' => ['Akira - Intégrale', 'Akira'];
        yield 'perfect edition'    => ['Slam Dunk - Perfect Edition', 'Slam Dunk'];
    }

    public function testKeepsDashedTitleWhenSuffixIsNotAnEdition(): void
    {
        // "Hunt - Beast Side" is a real title, not an edition variant.
        $this->assertTrue($this->matcher->mentions(
            'Hunt - Beast Side',
            'La Complete Edition du manga Hunt - Beast Side se précise',
        ));
    }

    public function testDashedTitleStillRequiresTheWholePhrase(): void
    {
        // A single shared word is not enough — the whole core phrase must appear.
        $this->assertFalse($this->matcher->mentions(
            'Hunt - Beast Side',
            'Une chasse au trésor (treasure hunt) dans ce nouveau shonen',
        ));
    }

    #[DataProvider('accentInsensitiveCases')]
    public function testMatchingIsAccentInsensitive(string $title, string $articleText): void
    {
        $this->assertTrue($this->matcher->mentions($title, $articleText));
    }

    /** @return iterable<string, array{string, string}> */
    public static function accentInsensitiveCases(): iterable
    {
        yield 'accent in title only'   => ['Pokémon', 'Le nouveau tome de Pokemon est disponible'];
        yield 'accent in article only' => ['Pokemon', 'Le nouveau tome de Pokémon est disponible'];
    }

    #[DataProvider('wordBoundaryCases')]
    public function testRespectsWordBoundaries(string $title, string $articleText, bool $expected): void
    {
        $this->assertSame($expected, $this->matcher->mentions($title, $articleText));
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function wordBoundaryCases(): iterable
    {
        yield 'exact word matches'        => ['Naruto', 'Le retour de Naruto annoncé', true];
        yield 'prefix does not match'     => ['Naruto', 'Bienvenue dans le Narutoverse', false];
        yield 'plural does not match'     => ['Dorohedoro', 'Tous les Dorohedoros réunis', false];
        yield 'multi-word phrase matches' => ['One Piece', 'Un nouvel arc pour One Piece', true];
        yield 'split phrase no match'     => ['One Piece', 'Une pièce maîtresse, the one', false];
    }

    #[DataProvider('emptyCases')]
    public function testEmptyInputsNeverMatch(string $title, string $articleText): void
    {
        $this->assertFalse($this->matcher->mentions($title, $articleText));
    }

    /** @return iterable<string, array{string, string}> */
    public static function emptyCases(): iterable
    {
        yield 'empty title'       => ['', 'Un article quelconque sur un manga'];
        yield 'empty article'     => ['Dorohedoro', ''];
        yield 'whitespace title'  => ['   ', 'Dorohedoro arrive'];
        yield 'punctuation title' => ['---', 'Dorohedoro arrive'];
    }

    #[DataProvider('coreTitleWordsCases')]
    public function testCoreTitleWordsExposesNormalizedTokens(string $title, array $expected): void
    {
        $this->assertSame($expected, $this->matcher->coreTitleWords($title));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function coreTitleWordsCases(): iterable
    {
        yield 'edition stripped' => ['Dorohedoro - Chaos edition', ['dorohedoro']];
        yield 'dashed title kept' => ['Hunt - Beast Side', ['hunt', 'beast', 'side']];
        yield 'accents folded'   => ['Pokémon', ['pokemon']];
        yield 'empty'            => ['', []];
    }
}
