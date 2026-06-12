<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Service;

use App\Manga\Domain\EditionFormatEnum;
use App\Manga\Domain\ExternalEditionDto;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Domain\Service\EditionCoverBackfiller;
use App\Tests\Doubles\Manga\StubMangaCoverProvider;
use PHPUnit\Framework\TestCase;

final class EditionCoverBackfillerTest extends TestCase
{
    private const string ISBN = '9782723425483';

    private function edition(?string $isbn, ?string $coverUrl): ExternalEditionDto
    {
        return new ExternalEditionDto(
            workTitle:    'Berserk',
            editionLabel: 'Glénat',
            publisher:    'Glénat',
            language:     'fr',
            country:      'FR',
            format:       EditionFormatEnum::Broche,
            volumeCount:  null,
            isbnSample:   $isbn,
            coverUrl:     $coverUrl,
            source:       'bnf',
        );
    }

    private function providerWithCover(string $coverUrl): StubMangaCoverProvider
    {
        $provider = new StubMangaCoverProvider();
        $provider->registerIsbn(self::ISBN, new MangaVolumeCoverDto($coverUrl, null, null, 'bnf'));

        return $provider;
    }

    public function testFillsMissingCoverFromIsbn(): void
    {
        $backfiller = new EditionCoverBackfiller($this->providerWithCover('https://cdn/cover.jpg'));

        $result = $backfiller->backfill([$this->edition(self::ISBN, null)]);

        $this->assertSame('https://cdn/cover.jpg', $result[0]->coverUrl);
    }

    public function testLeavesExistingCoverUntouched(): void
    {
        $backfiller = new EditionCoverBackfiller($this->providerWithCover('https://cdn/other.jpg'));

        $result = $backfiller->backfill([$this->edition(self::ISBN, 'https://existing/cover.jpg')]);

        $this->assertSame('https://existing/cover.jpg', $result[0]->coverUrl);
    }

    public function testFillsCoverFromContextWhenNoIsbn(): void
    {
        $provider = new StubMangaCoverProvider();
        $provider->registerContext(new MangaVolumeCoverDto('https://cdn/context.jpg', null, null, 'mangadex'));
        $backfiller = new EditionCoverBackfiller($provider);

        $result = $backfiller->backfill([$this->edition(null, null)]);

        $this->assertSame('https://cdn/context.jpg', $result[0]->coverUrl);
    }

    public function testKeepsCoverNullWhenNoIsbnAndNoContext(): void
    {
        $backfiller = new EditionCoverBackfiller(new StubMangaCoverProvider());

        $result = $backfiller->backfill([$this->edition(null, null)]);

        $this->assertNull($result[0]->coverUrl);
    }

    public function testKeepsCoverNullWhenIsbnHasNoMatch(): void
    {
        // An edition that carries an ISBN relies on it alone — no context fallback,
        // so a missing ISBN cover stays missing rather than borrowing a wrong cover.
        $backfiller = new EditionCoverBackfiller(new StubMangaCoverProvider());

        $result = $backfiller->backfill([$this->edition(self::ISBN, null)]);

        $this->assertNull($result[0]->coverUrl);
    }
}
