<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Country;
use App\Manga\Domain\EditionDiscoveryInterface;
use App\Manga\Domain\ExternalEditionDto;
use App\Manga\Infrastructure\ExternalApi\CompositeEditionDiscoveryClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CompositeEditionDiscoveryClientTest extends TestCase
{
    private function makeEdition(
        string $publisher,
        ?int $year,
        string $source,
        ?int $volumeCount = null,
    ): ExternalEditionDto {
        return new ExternalEditionDto(
            publisher: $publisher,
            editionLabel: null,
            year: $year,
            language: 'fr',
            coverUrl: null,
            volumeCount: $volumeCount,
            sampleIsbn: null,
            source: $source,
        );
    }

    public function testDeduplicatesEditionsByPublisherAndYear(): void
    {
        $googleEdition = $this->makeEdition('Glénat', 2019, 'google_books');
        $openLibEdition = $this->makeEdition('Glénat', 2019, 'open_library');

        $googleProvider = $this->createStub(EditionDiscoveryInterface::class);
        $googleProvider->method('discoverEditions')->willReturn([$googleEdition]);

        $openLibProvider = $this->createStub(EditionDiscoveryInterface::class);
        $openLibProvider->method('discoverEditions')->willReturn([$openLibEdition]);

        $composite = new CompositeEditionDiscoveryClient(
            [$googleProvider, $openLibProvider],
            new NullLogger(),
        );

        $editions = $composite->discoverEditions('Berserk', Country::France);

        $this->assertCount(1, $editions);
        $this->assertSame('google_books', $editions[0]->source);
    }

    public function testMergesUniqueEditionsFromBothProviders(): void
    {
        $googleEdition = $this->makeEdition('Glénat', 2019, 'google_books');
        $openLibEdition = $this->makeEdition('Panini', 2020, 'open_library');

        $googleProvider = $this->createStub(EditionDiscoveryInterface::class);
        $googleProvider->method('discoverEditions')->willReturn([$googleEdition]);

        $openLibProvider = $this->createStub(EditionDiscoveryInterface::class);
        $openLibProvider->method('discoverEditions')->willReturn([$openLibEdition]);

        $composite = new CompositeEditionDiscoveryClient(
            [$googleProvider, $openLibProvider],
            new NullLogger(),
        );

        $editions = $composite->discoverEditions('Berserk', Country::France);

        $this->assertCount(2, $editions);
    }

    public function testReturnsEmptyWhenNoProviders(): void
    {
        $composite = new CompositeEditionDiscoveryClient([], new NullLogger());
        $editions = $composite->discoverEditions('Berserk', Country::France);

        $this->assertSame([], $editions);
    }

    public function testSortsEditionsByVolumeCountDescending(): void
    {
        $singleVolume = $this->makeEdition('Hachette', 2008, 'bnf', 1);
        $manyVolumes = $this->makeEdition('Glénat', 1993, 'bnf', 21);
        $someVolumes = $this->makeEdition('Panini', 2020, 'google_books', 8);

        $firstProvider = $this->createStub(EditionDiscoveryInterface::class);
        $firstProvider->method('discoverEditions')->willReturn([$singleVolume, $manyVolumes]);

        $secondProvider = $this->createStub(EditionDiscoveryInterface::class);
        $secondProvider->method('discoverEditions')->willReturn([$someVolumes]);

        $composite = new CompositeEditionDiscoveryClient(
            [$firstProvider, $secondProvider],
            new NullLogger(),
        );

        $editions = $composite->discoverEditions('Dragon Ball', Country::France);

        $volumeCounts = array_map(static fn (ExternalEditionDto $edition): ?int => $edition->volumeCount, $editions);
        $this->assertSame([21, 8, 1], $volumeCounts);
    }
}
