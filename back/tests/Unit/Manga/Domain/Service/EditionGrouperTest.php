<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Service;

use App\Manga\Domain\Service\EditionGrouper;
use PHPUnit\Framework\TestCase;

final class EditionGrouperTest extends TestCase
{
    private EditionGrouper $grouper;

    protected function setUp(): void
    {
        $this->grouper = new EditionGrouper();
    }

    public function testGroupsThreeVolumesFromSamePublisher(): void
    {
        $rawVolumes = [
            ['publisher' => 'Glénat', 'year' => 2021, 'volumeNumber' => 3, 'title' => 'Berserk T03', 'coverUrl' => 'https://cover3.jpg', 'isbn' => null],
            ['publisher' => 'Glénat', 'year' => 2019, 'volumeNumber' => 1, 'title' => 'Berserk T01', 'coverUrl' => 'https://cover1.jpg', 'isbn' => '9782344020814'],
            ['publisher' => 'Glénat', 'year' => 2020, 'volumeNumber' => 2, 'title' => 'Berserk T02', 'coverUrl' => null, 'isbn' => null],
        ];

        $editions = $this->grouper->group($rawVolumes, 'fr');

        $this->assertCount(1, $editions);
        $this->assertSame('Glénat', $editions[0]->publisher);
        $this->assertSame(2019, $editions[0]->year);
        $this->assertSame(3, $editions[0]->volumeCount);
        $this->assertSame('https://cover1.jpg', $editions[0]->coverUrl);
        $this->assertSame('9782344020814', $editions[0]->sampleIsbn);
    }

    public function testGroupsTwoDistinctPublishers(): void
    {
        $rawVolumes = [
            ['publisher' => 'Glénat', 'year' => 2019, 'volumeNumber' => 1, 'title' => 'Berserk T01', 'coverUrl' => 'https://glenat.jpg', 'isbn' => null],
            ['publisher' => 'Panini', 'year' => 2020, 'volumeNumber' => 1, 'title' => 'Berserk Vol.1', 'coverUrl' => 'https://panini.jpg', 'isbn' => null],
        ];

        $editions = $this->grouper->group($rawVolumes, 'fr');

        $this->assertCount(2, $editions);
        $publishers = array_map(fn ($edition) => $edition->publisher, $editions);
        $this->assertContains('Glénat', $publishers);
        $this->assertContains('Panini', $publishers);
    }

    public function testDetectsPerfectEditionLabel(): void
    {
        $rawVolumes = [
            ['publisher' => 'Glénat', 'year' => 2022, 'volumeNumber' => 1, 'title' => 'Berserk Perfect Edition T01', 'coverUrl' => null, 'isbn' => null],
        ];

        $editions = $this->grouper->group($rawVolumes, 'fr');

        $this->assertCount(1, $editions);
        $this->assertSame('perfect', $editions[0]->editionLabel);
    }

    public function testDetectsDeluxeLabel(): void
    {
        $rawVolumes = [
            ['publisher' => 'Ki-oon', 'year' => 2021, 'volumeNumber' => 1, 'title' => 'Chainsaw Man Deluxe', 'coverUrl' => null, 'isbn' => null],
        ];

        $editions = $this->grouper->group($rawVolumes, 'fr');

        $this->assertSame('deluxe', $editions[0]->editionLabel);
    }

    public function testIgnoresItemsWithoutPublisher(): void
    {
        $rawVolumes = [
            ['publisher' => null, 'year' => 2019, 'volumeNumber' => 1, 'title' => 'Unknown', 'coverUrl' => null, 'isbn' => null],
            ['publisher' => '', 'year' => 2020, 'volumeNumber' => 2, 'title' => 'Empty', 'coverUrl' => null, 'isbn' => null],
            ['publisher' => 'Glénat', 'year' => 2021, 'volumeNumber' => 1, 'title' => 'Berserk', 'coverUrl' => 'https://cover.jpg', 'isbn' => null],
        ];

        $editions = $this->grouper->group($rawVolumes, 'fr');

        $this->assertCount(1, $editions);
        $this->assertSame('Glénat', $editions[0]->publisher);
    }

    public function testEmptyInputReturnsEmptyArray(): void
    {
        $editions = $this->grouper->group([], 'fr');

        $this->assertSame([], $editions);
    }

    public function testCoverPrioritizesVolumeOne(): void
    {
        $rawVolumes = [
            ['publisher' => 'Glénat', 'year' => 2021, 'volumeNumber' => 3, 'title' => 'T03', 'coverUrl' => 'https://cover3.jpg', 'isbn' => null],
            ['publisher' => 'Glénat', 'year' => 2019, 'volumeNumber' => 1, 'title' => 'T01', 'coverUrl' => 'https://cover1.jpg', 'isbn' => null],
        ];

        $editions = $this->grouper->group($rawVolumes, 'fr');

        $this->assertSame('https://cover1.jpg', $editions[0]->coverUrl);
    }

    public function testNoEditionLabelForStandardTitle(): void
    {
        $rawVolumes = [
            ['publisher' => 'Glénat', 'year' => 2019, 'volumeNumber' => 1, 'title' => 'Berserk T01', 'coverUrl' => null, 'isbn' => null],
        ];

        $editions = $this->grouper->group($rawVolumes, 'fr');

        $this->assertNull($editions[0]->editionLabel);
    }

    public function testSourceDefaultsToGrouped(): void
    {
        $rawVolumes = [
            ['publisher' => 'Glénat', 'year' => 2019, 'volumeNumber' => 1, 'title' => 'Berserk T01', 'coverUrl' => null, 'isbn' => null],
        ];

        $editions = $this->grouper->group($rawVolumes, 'fr');

        $this->assertSame('grouped', $editions[0]->source);
    }

    public function testSourceIsTaggedOnEachEdition(): void
    {
        $rawVolumes = [
            ['publisher' => 'Glénat', 'year' => 2019, 'volumeNumber' => 1, 'title' => 'Berserk T01', 'coverUrl' => null, 'isbn' => null],
        ];

        $editions = $this->grouper->group($rawVolumes, 'fr', 'bnf');

        $this->assertSame('bnf', $editions[0]->source);
    }
}
