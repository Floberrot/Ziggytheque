<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Service;

use App\Manga\Domain\Service\CatalogueTitleParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CatalogueTitleParserTest extends TestCase
{
    private CatalogueTitleParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CatalogueTitleParser();
    }

    /** @return iterable<string, array{string, string|null, string, string|null, int|null, string|null}> */
    public static function titles(): iterable
    {
        // BnF (ISBD) — the special edition sits between " : " and ". N"
        yield 'bnf standard run' => ['Berserk. 1', null, 'Berserk', null, 1, null];
        yield 'bnf edition with Vol. marker' => ['Berserk : édition deluxe. Vol. 1', null, 'Berserk', 'Édition deluxe', 1, null];
        yield 'bnf edition, volume title and authors' => [
            "Berserk : prestige. 12, L'épée du chevalier noir / Kentaro Miura", null,
            'Berserk', 'Prestige', 12, "L'épée du chevalier noir",
        ];
        yield 'bnf unpredictable edition name' => ['Naruto : édition Hokage. 1', null, 'Naruto', 'Édition Hokage', 1, null];
        yield 'bnf dot inside the title' => ['Dr. Stone. 3', null, 'Dr. Stone', null, 3, null];
        yield 'bnf number inside the title' => ['Kaiju n°8. 1', null, 'Kaiju n°8', null, 1, null];
        yield 'bnf bracketed note' => [
            "One piece [Texte imprimé] : édition originale. 1, À l'aube d'une grande aventure / Eiichirō Oda", null,
            'One piece', 'Édition originale', 1, "À l'aube d'une grande aventure",
        ];
        yield 'bnf one-shot companion' => ["Berserk : le guide de l'âge d'or", null, 'Berserk', "Le guide de l'âge d'or", null, null];
        yield 'google edition before the tome' => ['One Piece - Édition originale - Tome 12', null, 'One Piece', 'Édition originale', 12, null];
        yield 'google glued T marker' => ['Berserk T12', null, 'Berserk', null, 12, null];
        yield 'google marker wins over n°' => ['Kaiju n°8 - Tome 3', null, 'Kaiju n°8', null, 3, null];
        yield 'bnf parenthesised edition statement after the number' => [
            'Berserk. 5 (Éd. prestige)', null, 'Berserk', 'Édition prestige', 5, null,
        ];
        yield 'bnf parenthesised edition statement in the title' => [
            'Berserk (édition prestige). 5', null, 'Berserk', 'Édition prestige', 5, null,
        ];
        yield 'parentheses without an edition word stay in the title' => ['Akira (couleur). 2', null, 'Akira (couleur)', null, 2, null];
        yield 'abbreviated edition closing the qualifier' => ['Nausicaä : nouvelle éd. 1', null, 'Nausicaä', 'Nouvelle édition', 1, null];
        // Google Books — separators and explicit markers
        yield 'google edition statement after the tome' => ['Berserk - Tome 1 - Édition prestige', null, 'Berserk', 'Édition prestige', 1, null];
        yield 'google edition name ending with edition' => ['Berserk - Tome 1 - Perfect edition', null, 'Berserk', 'Perfect edition', 1, null];
        yield 'google volume title after the tome' => ['Berserk - Tome 1 - Le guerrier noir', null, 'Berserk', null, 1, 'Le guerrier noir'];
        yield 'google bare number after a separator' => ['Berserk - 5 (Éd. prestige)', null, 'Berserk', 'Édition prestige', 5, null];
        yield 'google bare number after a colon' => ['Spy x Family : 3', null, 'Spy x Family', null, 3, null];
        yield 'separator number wins over n°' => ['Kaiju n°8 - 3', null, 'Kaiju n°8', null, 3, null];
        yield 'a number glued to words is not a tome' => ['Naruto - 20 ans', null, 'Naruto', '20 ans', null, null];
        yield 'google subtitle carries the tome' => ['Berserk', 'Tome 1', 'Berserk', null, 1, null];
        yield 'google comma Vol.' => ['Berserk, Vol. 1', null, 'Berserk', null, 1, null];
        yield 'no marker: the trailing number is part of the title' => ['Mob Psycho 100', null, 'Mob Psycho 100', null, null, null];
        yield 'generic qualifier is not an edition' => ['Berserk - Manga - Tome 2', null, 'Berserk', null, 2, null];
    }

    #[DataProvider('titles')]
    public function testParsesTheTitleStructure(
        string $title,
        ?string $subtitle,
        string $expectedWork,
        ?string $expectedHeadQualifier,
        ?int $expectedVolume,
        ?string $expectedTrailingQualifier,
    ): void {
        $parsed = $this->parser->parse($title, $subtitle);

        $this->assertSame($expectedWork, $parsed->workTitle);
        $this->assertSame($expectedHeadQualifier, $parsed->headQualifier);
        $this->assertSame($expectedVolume, $parsed->volumeNumber);
        $this->assertSame($expectedTrailingQualifier, $parsed->trailingQualifier);
    }

    public function testCapsTheQualifierToTheColumnLength(): void
    {
        $parsed = $this->parser->parse('Berserk : ' . str_repeat('a', 200) . '. 1');

        $this->assertSame(150, mb_strlen((string) $parsed->headQualifier));
    }

    /** @return iterable<string, array{string, string, int|null}> */
    public static function queries(): iterable
    {
        yield 'plain title' => ['berserk', 'berserk', null];
        yield 'bare trailing tome number' => ['berserk prestige 5', 'berserk prestige', 5];
        yield 'explicit tome marker' => ['one piece tome 3', 'one piece', 3];
        yield 'extra spaces' => ['  berserk   12 ', 'berserk', 12];
    }

    #[DataProvider('queries')]
    public function testParseQuerySplitsTheRequestedTome(string $query, string $expectedText, ?int $expectedVolume): void
    {
        $this->assertSame(
            ['text' => $expectedText, 'volumeNumber' => $expectedVolume],
            $this->parser->parseQuery($query),
        );
    }
}
