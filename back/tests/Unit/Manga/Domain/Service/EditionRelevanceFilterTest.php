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
        $this->assertTrue($this->filter->isRelevant('Berserk', 'Berserk, Vol. 1', 'Glénat (Grenoble)', 'text'));
        $this->assertTrue($this->filter->isRelevant('Berserk', 'Berserk : édition deluxe', 'Dark Horse Comics', 'text'));
    }

    public function testKeepsArtbooksAndCompanionBooks(): void
    {
        // Artbooks are collectible editions now — never rejected on their title.
        $this->assertTrue($this->filter->isRelevant('Berserk', 'Berserk illustrations file', 'Glénat'));
        $this->assertTrue($this->filter->isRelevant('Berserk', 'The Artwork of Berserk', null));
        $this->assertTrue($this->filter->isRelevant('Dragon Ball', 'Dragon Ball artbook', 'Glénat'));
        $this->assertTrue($this->filter->isRelevant('進撃の巨人', '進撃の巨人 公式ガイドブック', '講談社'));
        $this->assertTrue($this->filter->isRelevant('One Piece', 'One Piece official fan book', 'Shueisha'));
    }

    public function testKeepsEditionWithUnknownPublisherWhenTitleMatches(): void
    {
        $this->assertTrue($this->filter->isRelevant('Dragon Ball', 'Dragon Ball, tome 1', 'Tartempion Éditions'));
    }

    public function testKeepsEditionWithEmptyPublisherWhenTitleMatches(): void
    {
        $this->assertTrue($this->filter->isRelevant('Dragon Ball', 'Dragon Ball : coffret tomes 1 à 5', null));
        $this->assertTrue($this->filter->isRelevant('Dragon Ball', 'Dragon Ball, tome 1', '   '));
    }

    public function testRejectsUnrelatedTitleFromUnknownPublisher(): void
    {
        // No title match, no recognised manga house → off-topic record.
        $this->assertFalse($this->filter->isRelevant('Berserk', 'La cuisine japonaise pour tous', 'Tartempion Éditions'));
        $this->assertFalse($this->filter->isRelevant('Berserk', 'Naruto, tome 1', null));
    }

    public function testKeepsTranslatedTitleFromKnownMangaPublisher(): void
    {
        // A Japanese edition surfacing under the western work title: the record title
        // does not literally match, but the publisher is a recognised manga house.
        $this->assertTrue($this->filter->isRelevant('Berserk', 'ベルセルク 1', '白泉社'));
        $this->assertTrue($this->filter->isRelevant('Attack on Titan', '進撃の巨人 1', 'Kodansha'));
    }

    public function testTitleMatchIsAccentAndCaseInsensitive(): void
    {
        $this->assertTrue($this->filter->isRelevant("L'Attaque des Titans", "L'attaque des titans : édition colossale", null));
        $this->assertTrue($this->filter->isRelevant('Berserk', 'BERSERK — Coffret intégrale', null));
    }

    public function testTitleMatchRequiresEverySignificantWord(): void
    {
        // "Titans" alone is not the searched work.
        $this->assertFalse($this->filter->isRelevant("L'Attaque des Titans", 'La guerre des titans', null));
    }

    public function testRejectsVideoByType(): void
    {
        $this->assertFalse($this->filter->isRelevant('Dragon Ball Z', 'Dragon Ball Z', 'Kana', 'image animée'));
        $this->assertFalse($this->filter->isRelevant('Dragon Ball Z', 'Dragon Ball Z', 'Kana', 'vidéo'));
    }

    public function testRejectsDeniedPublishers(): void
    {
        $this->assertFalse($this->filter->isRelevant('Dragon Ball', 'Dragon Ball', 'AB vidéo (La Plaine Saint-Denis)'));
        $this->assertFalse($this->filter->isRelevant('Dragon Ball', 'Dragon Ball', 'Hachette collections (Vanves)'));
        $this->assertFalse($this->filter->isRelevant('Dragon Ball', 'Dragon Ball', 'Éd. Atlas (Évreux)'));
        $this->assertFalse($this->filter->isRelevant('Berserk', "Berserk : le guide de l'âge d'or", 'Third Editions'));
    }

    public function testRejectsNoiseByTitle(): void
    {
        $this->assertFalse($this->filter->isRelevant('Dragon Ball', 'Dragon Ball : coloriages', 'Glénat'));
        $this->assertFalse($this->filter->isRelevant('Dragon Ball', 'Agenda Dragon Ball 2026', null));
        $this->assertFalse($this->filter->isRelevant('Dragon Ball', 'Calendrier Dragon Ball', null));
        $this->assertFalse($this->filter->isRelevant('Naruto', 'Naruto : guide de lecture non officiel', null));
        $this->assertFalse($this->filter->isRelevant('Dragon Ball', 'Dragon Ball : the novel', 'Glénat'));
        $this->assertFalse($this->filter->isRelevant('Dragon Ball', 'Dragon Ball : figurine Son Goku', null));
        $this->assertFalse($this->filter->isRelevant('Berserk', 'Berserk : stickers officiels', null));
    }

    public function testDoesNotFalseRejectRomance(): void
    {
        // "roman" must not match inside "romance".
        $this->assertTrue($this->filter->isRelevant('Romance shojo', 'Romance shojo, tome 1', 'Kana'));
    }

    public function testKeepsKnownMangaPublishersAcrossMarkets(): void
    {
        $this->assertTrue($this->filter->isRelevant('Dragon Ball', 'Dragon Ball, Vol. 1', 'Carlsen Manga'));
        $this->assertTrue($this->filter->isRelevant('Dragon Ball', 'Dragon Ball, Vol. 1', 'VIZ Media LLC'));
        $this->assertTrue($this->filter->isRelevant('Dragon Ball', 'ドラゴンボール 1', 'Shueisha'));
        $this->assertTrue($this->filter->isRelevant('Dragon Ball', 'Dragon Ball, Vol. 1', 'Star Comics'));
        $this->assertTrue($this->filter->isRelevant('Dragon Ball', 'Dragon Ball, Vol. 1', 'Planeta Cómic'));
    }

    public function testKeepsJapaneseEditionWithKanjiPublisher(): void
    {
        // Berserk's Japanese publisher (白泉社 / Hakusensha) arrives in kanji.
        $this->assertTrue($this->filter->isRelevant('ベルセルク', 'ベルセルク 1', '白泉社'));
        $this->assertTrue($this->filter->isRelevant('ドラゴンボール', 'ドラゴンボール 1', '株式会社集英社'));
    }

    public function testRejectsVideoArmButKeepsMangaArm(): void
    {
        $this->assertFalse($this->filter->isRelevant('Dragon Ball Z', 'Dragon Ball Z', 'Kazé Video'));
        $this->assertTrue($this->filter->isRelevant('Dragon Ball', 'Dragon Ball, tome 1', 'Kazé Manga'));
    }
}
