<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\ExternalEditionDto;
use PHPUnit\Framework\TestCase;

final class ExternalEditionDtoTest extends TestCase
{
    public function testConstructionAndToArray(): void
    {
        $dto = new ExternalEditionDto(
            publisher: 'Glénat',
            editionLabel: 'deluxe',
            year: 2019,
            language: 'fr',
            coverUrl: 'https://example.com/cover.jpg',
            volumeCount: 40,
            sampleIsbn: '9782344020814',
            source: 'google_books',
        );

        $this->assertSame('Glénat', $dto->publisher);
        $this->assertSame('deluxe', $dto->editionLabel);
        $this->assertSame(2019, $dto->year);
        $this->assertSame('fr', $dto->language);
        $this->assertSame('https://example.com/cover.jpg', $dto->coverUrl);
        $this->assertSame(40, $dto->volumeCount);
        $this->assertSame('9782344020814', $dto->sampleIsbn);
        $this->assertSame('google_books', $dto->source);

        $array = $dto->toArray();
        $this->assertSame('Glénat', $array['publisher']);
        $this->assertSame('deluxe', $array['editionLabel']);
        $this->assertSame(2019, $array['year']);
        $this->assertSame('fr', $array['language']);
        $this->assertSame('https://example.com/cover.jpg', $array['coverUrl']);
        $this->assertSame(40, $array['volumeCount']);
        $this->assertSame('9782344020814', $array['sampleIsbn']);
        $this->assertSame('google_books', $array['source']);
    }

    public function testNullableFields(): void
    {
        $dto = new ExternalEditionDto(
            publisher: 'Ki-oon',
            editionLabel: null,
            year: null,
            language: 'fr',
            coverUrl: null,
            volumeCount: null,
            sampleIsbn: null,
            source: 'open_library',
        );

        $array = $dto->toArray();
        $this->assertNull($array['editionLabel']);
        $this->assertNull($array['year']);
        $this->assertNull($array['coverUrl']);
        $this->assertNull($array['volumeCount']);
        $this->assertNull($array['sampleIsbn']);
    }
}
