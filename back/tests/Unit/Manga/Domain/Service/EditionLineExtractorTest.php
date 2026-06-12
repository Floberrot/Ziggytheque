<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Service;

use App\Manga\Domain\Service\EditionLineExtractor;
use PHPUnit\Framework\TestCase;

final class EditionLineExtractorTest extends TestCase
{
    private EditionLineExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new EditionLineExtractor();
    }

    public function testDetectsPerfectEdition(): void
    {
        $this->assertSame('Perfect Edition', $this->extractor->extract('Dragon Ball : perfect edition. 1'));
        $this->assertSame('Perfect Edition', $this->extractor->extract('Dragon Ball kanzenban'));
    }

    public function testDetectsEditionOriginale(): void
    {
        $this->assertSame('Édition originale', $this->extractor->extract('Berserk : édition originale. 1'));
    }

    public function testDetectsDeluxe(): void
    {
        $this->assertSame('Deluxe', $this->extractor->extract('Berserk : édition deluxe. Vol. 1'));
        $this->assertSame('Deluxe', $this->extractor->extract('Berserk Deluxe, Vol. 1', 'Deluxe edition'));
    }

    public function testDetectsCoffret(): void
    {
        $this->assertSame('Coffret', $this->extractor->extract('Dragon Ball : coffret tomes 1 à 5'));
    }

    public function testDetectsJapaneseKanjiEditionMarkers(): void
    {
        $this->assertSame('Perfect Edition', $this->extractor->extract('ベルセルク 完全版 1'));
        $this->assertSame('Édition couleur', $this->extractor->extract('進撃の巨人 カラー版 1'));
        $this->assertSame('Deluxe', $this->extractor->extract('ドラゴンボール 愛蔵版 2'));
        $this->assertSame('Nouvelle édition', $this->extractor->extract('幽☆遊☆白書 新装版'));
        $this->assertSame('Bunko', $this->extractor->extract('スラムダンク 文庫版 5'));
        $this->assertSame('Intégrale', $this->extractor->extract('ナルト 総集編 1'));
    }

    public function testPreservesDistinctSpecialEditionNames(): void
    {
        $this->assertSame('Prestige', $this->extractor->extract('Berserk : édition prestige. 1'));
        $this->assertSame('Maximum', $this->extractor->extract('Berserk Maximum, Vol. 1'));
        $this->assertSame('Ultimate', $this->extractor->extract('Berserk Ultimate Edition'));
        $this->assertSame('Collector', $this->extractor->extract('Berserk : édition collector'));
    }

    public function testDetectsIntegraleAndThreeInOne(): void
    {
        $this->assertSame('Intégrale', $this->extractor->extract('Dragon Ball : intégrale'));
        $this->assertSame('Intégrale', $this->extractor->extract('Dragon Ball 3-in-1, Vol. 1'));
    }

    public function testDetectsNamedEditionsGenerically(): void
    {
        $this->assertSame('Édition pastel', $this->extractor->extract('Dragon Ball : édition pastel. 1'));
        $this->assertSame('Édition spéciale', $this->extractor->extract('Dragon Ball : édition spéciale'));
        $this->assertSame('Édition limitée', $this->extractor->extract('Naruto édition limitée, tome 3'));
        $this->assertSame('Anniversary Edition', $this->extractor->extract('One Piece anniversary edition'));
    }

    public function testGenericFallbackIgnoresLanguageAndBindingStatements(): void
    {
        $this->assertNull($this->extractor->extract('Dragon Ball : édition française'));
        $this->assertNull($this->extractor->extract('Dragon Ball : édition simple. 1'));
        $this->assertNull($this->extractor->extract('Dragon Ball : édition brochée'));
    }

    public function testReturnsNullForPlainVolume(): void
    {
        $this->assertNull($this->extractor->extract('Berserk, Vol. 1'));
        $this->assertNull($this->extractor->extract('Dragon Ball. 12'));
    }

    public function testReturnsNullForEmptyInput(): void
    {
        $this->assertNull($this->extractor->extract());
        $this->assertNull($this->extractor->extract('', ''));
    }
}
