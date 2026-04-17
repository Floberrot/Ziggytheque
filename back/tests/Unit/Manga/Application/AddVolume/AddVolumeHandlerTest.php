<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\AddVolume;

use App\Manga\Application\AddVolume\AddVolumeCommand;
use App\Manga\Application\AddVolume\AddVolumeHandler;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\PriceCode\Domain\PriceCode;
use App\PriceCode\Domain\PriceCodeRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class AddVolumeHandlerTest extends TestCase
{
    public function testAddsVolumeToMangaAndReturnsId(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $mangaRepo = $this->createMock(MangaRepositoryInterface::class);
        $mangaRepo->method('findById')->willReturn($manga);
        $mangaRepo->expects($this->once())->method('save');

        $priceCodeRepo = $this->createMock(PriceCodeRepositoryInterface::class);

        $handler = new AddVolumeHandler($mangaRepo, $priceCodeRepo);
        $id = $handler(new AddVolumeCommand('m-1', 1));

        $this->assertNotEmpty($id);
        $this->assertCount(1, $manga->volumes);
    }

    public function testResolvesOptionalPriceCode(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $pc = new PriceCode('POCHE', 'Poche', 6.99);
        $mangaRepo = $this->createMock(MangaRepositoryInterface::class);
        $mangaRepo->method('findById')->willReturn($manga);
        $mangaRepo->expects($this->once())->method('save');
        $priceCodeRepo = $this->createMock(PriceCodeRepositoryInterface::class);
        $priceCodeRepo->method('findByCode')->with('POCHE')->willReturn($pc);

        $handler = new AddVolumeHandler($mangaRepo, $priceCodeRepo);
        $handler(new AddVolumeCommand('m-1', 1, null, 'POCHE'));

        $volumes = $manga->volumes->toArray();
        $this->assertSame($pc, $volumes[0]->priceCode);
    }

    public function testThrowsWhenMangaNotFound(): void
    {
        $mangaRepo = $this->createMock(MangaRepositoryInterface::class);
        $mangaRepo->method('findById')->willReturn(null);
        $priceCodeRepo = $this->createMock(PriceCodeRepositoryInterface::class);

        $handler = new AddVolumeHandler($mangaRepo, $priceCodeRepo);

        $this->expectException(NotFoundException::class);
        $handler(new AddVolumeCommand('missing', 1));
    }
}
