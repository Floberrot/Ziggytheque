<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\ExternalVolumeDto;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ExternalVolumeDtoTest extends TestCase
{
    public function testConstruction(): void
    {
        $date = new DateTimeImmutable('2024-01-15');
        $dto  = new ExternalVolumeDto(number: 5, coverUrl: 'https://example.com/5.jpg', releaseDate: $date);

        $this->assertSame(5, $dto->number);
        $this->assertSame('https://example.com/5.jpg', $dto->coverUrl);
        $this->assertSame($date, $dto->releaseDate);
    }

    public function testNullable(): void
    {
        $dto = new ExternalVolumeDto(number: 1, coverUrl: null, releaseDate: null);

        $this->assertNull($dto->coverUrl);
        $this->assertNull($dto->releaseDate);
    }
}
