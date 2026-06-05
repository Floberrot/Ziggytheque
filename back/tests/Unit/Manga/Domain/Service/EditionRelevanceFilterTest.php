<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Service;

use App\Manga\Domain\Service\EditionRelevanceFilter;
use App\Manga\Domain\Service\PublisherNormalizer;
use PHPUnit\Framework\TestCase;

final class EditionRelevanceFilterTest extends TestCase
{
    private EditionRelevanceFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new EditionRelevanceFilter(new PublisherNormalizer());
    }

    public function testKeepsLegitimateMangaEdition(): void
    {
        $this->assertTrue($this->filter->isRelevant('Berserk, Vol. 1', 'Glénat (Grenoble)', 'text'));
        $this->assertTrue($this->filter->isRelevant('Berserk : édition deluxe', 'Dark Horse Comics', 'text'));
    }

    public function testRejectsVideoByType(): void
    {
        $this->assertFalse($this->filter->isRelevant('Dragon Ball Z', 'Kana', 'image animée'));
        $this->assertFalse($this->filter->isRelevant('Dragon Ball Z', 'Kana', 'vidéo'));
    }

    public function testRejectsDeniedPublishers(): void
    {
        $this->assertFalse($this->filter->isRelevant('Dragon Ball', 'AB vidéo (La Plaine Saint-Denis)'));
        $this->assertFalse($this->filter->isRelevant('Dragon Ball', 'Hachette collections (Vanves)'));
        $this->assertFalse($this->filter->isRelevant('Dragon Ball', 'Éd. Atlas (Évreux)'));
        $this->assertFalse($this->filter->isRelevant('Dragon Ball', 'Third Editions'));
    }

    public function testRejectsDerivativePrintByTitle(): void
    {
        $this->assertFalse($this->filter->isRelevant("Berserk : le guide de l'âge d'or", 'Glénat'));
        $this->assertFalse($this->filter->isRelevant('Dragon Ball : the world of Akira Toriyama', 'Glénat'));
        $this->assertFalse($this->filter->isRelevant('Dragon Ball artbook', 'Glénat'));
        $this->assertFalse($this->filter->isRelevant('Dragon Ball : the novel', 'Glénat'));
    }

    public function testDoesNotFalseRejectRomance(): void
    {
        // "roman" must not match inside "romance".
        $this->assertTrue($this->filter->isRelevant('Romance shojo, tome 1', 'Kana'));
    }

    public function testKeepsKnownMangaPublishersAcrossMarkets(): void
    {
        $this->assertTrue($this->filter->isRelevant('Dragon Ball, Vol. 1', 'Carlsen Manga'));
        $this->assertTrue($this->filter->isRelevant('Dragon Ball, Vol. 1', 'VIZ Media LLC'));
        $this->assertTrue($this->filter->isRelevant('ドラゴンボール 1', 'Shueisha'));
        $this->assertTrue($this->filter->isRelevant('Dragon Ball, Vol. 1', 'Star Comics'));
        $this->assertTrue($this->filter->isRelevant('Dragon Ball, Vol. 1', 'Planeta Cómic'));
    }

    public function testKeepsJapaneseEditionWithKanjiPublisher(): void
    {
        // Berserk's Japanese publisher (白泉社 / Hakusensha) arrives in kanji.
        $this->assertTrue($this->filter->isRelevant('ベルセルク 1', '白泉社'));
        $this->assertTrue($this->filter->isRelevant('ドラゴンボール 1', '株式会社集英社'));
    }

    public function testRejectsUnknownPublisher(): void
    {
        // Not a recognised manga house → dropped, however clean the record looks.
        $this->assertFalse($this->filter->isRelevant('Dragon Ball, tome 1', 'Tartempion Éditions'));
    }

    public function testRejectsEmptyPublisher(): void
    {
        $this->assertFalse($this->filter->isRelevant('Dragon Ball, tome 1', null));
        $this->assertFalse($this->filter->isRelevant('Dragon Ball, tome 1', '   '));
    }

    public function testRejectsVideoArmButKeepsMangaArm(): void
    {
        $this->assertFalse($this->filter->isRelevant('Dragon Ball Z', 'Kazé Video'));
        $this->assertTrue($this->filter->isRelevant('Dragon Ball, tome 1', 'Kazé Manga'));
    }
}
