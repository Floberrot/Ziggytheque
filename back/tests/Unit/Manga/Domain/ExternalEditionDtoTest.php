<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\EditionFormatEnum;
use App\Manga\Domain\ExternalEditionDto;
use PHPUnit\Framework\TestCase;

final class ExternalEditionDtoTest extends TestCase
{
    private function makeDto(): ExternalEditionDto
    {
        return new ExternalEditionDto(
            workTitle:    'Berserk',
            editionLabel: 'Glénat — édition Maximum',
            publisher:    'Glénat',
            language:     'fr',
            country:      'FR',
            format:       EditionFormatEnum::Deluxe,
            volumeCount:  12,
            isbnSample:   '9782344001234',
            coverUrl:     'https://example.com/cover.jpg',
            source:       'bnf',
            externalId:   'ext-001',
            editionLine:  'Deluxe',
        );
    }

    public function testConstructionSetsAllFields(): void
    {
        $dto = $this->makeDto();

        $this->assertSame('Berserk', $dto->workTitle);
        $this->assertSame('Glénat — édition Maximum', $dto->editionLabel);
        $this->assertSame('Glénat', $dto->publisher);
        $this->assertSame('fr', $dto->language);
        $this->assertSame('FR', $dto->country);
        $this->assertSame(EditionFormatEnum::Deluxe, $dto->format);
        $this->assertSame(12, $dto->volumeCount);
        $this->assertSame('9782344001234', $dto->isbnSample);
        $this->assertSame('https://example.com/cover.jpg', $dto->coverUrl);
        $this->assertSame('bnf', $dto->source);
        $this->assertSame('ext-001', $dto->externalId);
        $this->assertSame('Deluxe', $dto->editionLine);
    }

    public function testWithCoverUrlReturnsCopyWithCoverSet(): void
    {
        $dto     = $this->makeDto();
        $updated = $dto->withCoverUrl('https://cdn.example/new.jpg');

        $this->assertSame('https://cdn.example/new.jpg', $updated->coverUrl);
        // Every other field is preserved on the copy.
        $this->assertSame($dto->publisher, $updated->publisher);
        $this->assertSame($dto->editionLine, $updated->editionLine);
        $this->assertSame($dto->isbnSample, $updated->isbnSample);
        // Original is untouched (readonly value object).
        $this->assertSame('https://example.com/cover.jpg', $dto->coverUrl);
    }

    public function testToArrayContainsAllKeys(): void
    {
        $dto   = $this->makeDto();
        $array = $dto->toArray();

        $this->assertArrayHasKey('workTitle', $array);
        $this->assertArrayHasKey('editionLabel', $array);
        $this->assertArrayHasKey('publisher', $array);
        $this->assertArrayHasKey('language', $array);
        $this->assertArrayHasKey('country', $array);
        $this->assertArrayHasKey('format', $array);
        $this->assertArrayHasKey('volumeCount', $array);
        $this->assertArrayHasKey('isbnSample', $array);
        $this->assertArrayHasKey('coverUrl', $array);
        $this->assertArrayHasKey('source', $array);
        $this->assertArrayHasKey('externalId', $array);
        $this->assertArrayHasKey('editionLine', $array);
    }

    public function testToArraySerializesFormatAsValue(): void
    {
        $dto = $this->makeDto();

        $this->assertSame('deluxe', $dto->toArray()['format']);
    }

    public function testToArrayWithNullables(): void
    {
        $dto = new ExternalEditionDto(
            workTitle:    'Berserk',
            editionLabel: 'Berserk',
            publisher:    null,
            language:     'fr',
            country:      null,
            format:       EditionFormatEnum::Unknown,
            volumeCount:  null,
            isbnSample:   null,
            coverUrl:     null,
            source:       'bnf',
        );

        $array = $dto->toArray();

        $this->assertNull($array['publisher']);
        $this->assertNull($array['country']);
        $this->assertNull($array['volumeCount']);
        $this->assertNull($array['isbnSample']);
        $this->assertNull($array['coverUrl']);
        $this->assertNull($array['externalId']);
        $this->assertNull($array['editionLine']);
        $this->assertSame('unknown', $array['format']);
    }
}
