<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Service;

use App\Manga\Domain\EditionFormatEnum;
use App\Manga\Domain\ExternalEditionDto;
use App\Manga\Domain\Service\EditionGrouper;
use App\Manga\Domain\Service\PublisherNormalizer;
use PHPUnit\Framework\TestCase;

final class EditionGrouperTest extends TestCase
{
    private EditionGrouper $grouper;

    protected function setUp(): void
    {
        $this->grouper = new EditionGrouper(new PublisherNormalizer());
    }

    private function makeDto(
        string $publisher,
        string $language,
        EditionFormatEnum $format,
        ?int $volumeCount = null,
        ?string $coverUrl = null,
        ?string $isbnSample = null,
        ?string $country = null,
        ?string $editionLine = null,
    ): ExternalEditionDto {
        return new ExternalEditionDto(
            workTitle:    'Berserk',
            editionLabel: sprintf('%s — Berserk', $publisher),
            publisher:    $publisher,
            language:     $language,
            country:      $country ?? ($language === 'fr' ? 'FR' : 'US'),
            format:       $format,
            volumeCount:  $volumeCount,
            isbnSample:   $isbnSample,
            coverUrl:     $coverUrl,
            source:       'bnf',
            editionLine:  $editionLine,
        );
    }

    public function testTwoDtosWithSamePublisherLanguageFormatAreMergedIntoOne(): void
    {
        $dtoA = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche, volumeCount: 5);
        $dtoB = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche, volumeCount: 12);

        $result = $this->grouper->group([$dtoA, $dtoB]);

        $this->assertCount(1, $result);
        $this->assertSame(12, $result[0]->volumeCount);
    }

    public function testMergeKeepsFirstNonNullCoverUrl(): void
    {
        $dtoA = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche, coverUrl: null);
        $dtoB = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche, coverUrl: 'https://example.com/cover.jpg');

        $result = $this->grouper->group([$dtoA, $dtoB]);

        $this->assertCount(1, $result);
        $this->assertSame('https://example.com/cover.jpg', $result[0]->coverUrl);
    }

    public function testMergeKeepsFirstNonNullIsbnSample(): void
    {
        $dtoA = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche, isbnSample: null);
        $dtoB = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche, isbnSample: '9782344001234');

        $result = $this->grouper->group([$dtoA, $dtoB]);

        $this->assertCount(1, $result);
        $this->assertSame('9782344001234', $result[0]->isbnSample);
    }

    public function testTwoDtosWithDifferentFormatsProduceTwoLines(): void
    {
        $broche = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche);
        $deluxe = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Deluxe);

        $result = $this->grouper->group([$broche, $deluxe]);

        $this->assertCount(2, $result);
    }

    public function testTwoDtosWithDifferentLanguagesProduceTwoLines(): void
    {
        $french  = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche);
        $english = $this->makeDto('Glénat', 'en', EditionFormatEnum::Broche);

        $result = $this->grouper->group([$french, $english]);

        $this->assertCount(2, $result);
    }

    public function testResultIsSortedFrFirstThenUs(): void
    {
        $us = $this->makeDto('Dark Horse', 'en', EditionFormatEnum::Relie, country: 'US');
        $fr = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche, country: 'FR');

        $result = $this->grouper->group([$us, $fr]);

        $this->assertCount(2, $result);
        $this->assertSame('FR', $result[0]->country);
        $this->assertSame('US', $result[1]->country);
    }

    public function testEmptyInputReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->grouper->group([]));
    }

    public function testVolumeCountMaxIsTaken(): void
    {
        $dtoA = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche, volumeCount: null);
        $dtoB = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche, volumeCount: 7);
        $dtoC = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche, volumeCount: 3);

        $result = $this->grouper->group([$dtoA, $dtoB, $dtoC]);

        $this->assertCount(1, $result);
        $this->assertSame(7, $result[0]->volumeCount);
    }

    public function testPublisherCityVariantsAreMergedAndCleaned(): void
    {
        $withCity = $this->makeDto('Glénat (Grenoble)', 'fr', EditionFormatEnum::Broche);
        $plain    = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche);

        $result = $this->grouper->group([$withCity, $plain]);

        $this->assertCount(1, $result);
        $this->assertSame('Glénat', $result[0]->publisher);
    }

    public function testVizAliasesCollapseIntoOnePublisher(): void
    {
        $alpha   = $this->makeDto('Viz Media', 'en', EditionFormatEnum::Broche);
        $bravo   = $this->makeDto('VIZ Media LLC', 'en', EditionFormatEnum::Broche);
        $charlie = $this->makeDto('Viz Communications', 'en', EditionFormatEnum::Broche);

        $result = $this->grouper->group([$alpha, $bravo, $charlie]);

        $this->assertCount(1, $result);
        $this->assertSame('Viz Media', $result[0]->publisher);
    }

    public function testDifferentEditionLinesOfSamePublisherStaySeparate(): void
    {
        $perfect  = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche, editionLine: 'Perfect Edition');
        $original = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche, editionLine: 'Édition originale');

        $result = $this->grouper->group([$perfect, $original]);

        $this->assertCount(2, $result);
    }

    public function testLabelEmbedsPublisherAndEditionLine(): void
    {
        $perfect = $this->makeDto('Glénat (Grenoble)', 'fr', EditionFormatEnum::Broche, editionLine: 'Perfect Edition');

        $result = $this->grouper->group([$perfect]);

        $this->assertCount(1, $result);
        $this->assertSame('Glénat — Perfect Edition', $result[0]->editionLabel);
    }

    public function testDistinctImprintsSharingDisplayNameStaySeparate(): void
    {
        // Tonkam's run and a Delcourt run both display as "Delcourt/Tonkam", but they
        // are distinct editorial lines — the display merge must not group them.
        $tonkam   = $this->makeDto('Tonkam', 'fr', EditionFormatEnum::Broche, isbnSample: '9782845803350');
        $delcourt = $this->makeDto('Delcourt', 'fr', EditionFormatEnum::Broche, isbnSample: '9782756071121');

        $result = $this->grouper->group([$tonkam, $delcourt]);

        $this->assertCount(2, $result);
        foreach ($result as $edition) {
            $this->assertSame('Delcourt/Tonkam', $edition->publisher);
        }
    }

    public function testConflictingIsbnRegistrationGroupsAreNotMerged(): void
    {
        // Same normalised publisher + language, but a 978-2 (French) ISBN and a 978-4
        // (Japanese) ISBN cannot belong to the same edition line.
        $french   = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche, isbnSample: '9782344001233');
        $japanese = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche, isbnSample: '9784063842760');

        $result = $this->grouper->group([$french, $japanese]);

        $this->assertCount(2, $result);
    }

    public function testEnglishIsbnGroupsZeroAndOneAreMergedTogether(): void
    {
        // 978-0 and 978-1 are both the English-speaking registration area — a US line
        // printing under both must stay one entry.
        $groupZero = $this->makeDto('Dark Horse', 'en', EditionFormatEnum::Broche, isbnSample: '9780123456786');
        $groupOne  = $this->makeDto('Dark Horse', 'en', EditionFormatEnum::Broche, isbnSample: '9781593070205');

        $result = $this->grouper->group([$groupZero, $groupOne]);

        $this->assertCount(1, $result);
    }

    public function testDtosWithoutIsbnNeverForceASplit(): void
    {
        $withIsbn    = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche, isbnSample: '9782344001233');
        $withoutIsbn = $this->makeDto('Glénat', 'fr', EditionFormatEnum::Broche, isbnSample: null);

        $result = $this->grouper->group([$withIsbn, $withoutIsbn]);

        $this->assertCount(1, $result);
        $this->assertSame('9782344001233', $result[0]->isbnSample);
    }
}
