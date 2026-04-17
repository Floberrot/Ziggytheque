<?php

declare(strict_types=1);

namespace App\Tests\Unit\PriceCode\Application\Update;

use App\PriceCode\Application\Update\UpdatePriceCodeCommand;
use App\PriceCode\Application\Update\UpdatePriceCodeHandler;
use App\PriceCode\Domain\Exception\PriceCodeNotFoundException;
use App\PriceCode\Domain\PriceCode;
use App\PriceCode\Domain\PriceCodeRepositoryInterface;
use PHPUnit\Framework\TestCase;

class UpdatePriceCodeHandlerTest extends TestCase
{
    public function testUpdatesExistingPriceCode(): void
    {
        $pc = new PriceCode('POCHE', 'Old', 5.0);
        $repository = $this->createMock(PriceCodeRepositoryInterface::class);
        $repository->method('findByCode')->with('POCHE')->willReturn($pc);
        $repository->expects($this->once())->method('save')->with($pc);

        $handler = new UpdatePriceCodeHandler($repository);
        $handler(new UpdatePriceCodeCommand('POCHE', 'New', 7.5));

        $this->assertSame('New', $pc->label);
        $this->assertSame(7.5, $pc->value);
    }

    public function testThrowsWhenNotFound(): void
    {
        $repository = $this->createMock(PriceCodeRepositoryInterface::class);
        $repository->method('findByCode')->willReturn(null);

        $handler = new UpdatePriceCodeHandler($repository);

        $this->expectException(PriceCodeNotFoundException::class);
        $handler(new UpdatePriceCodeCommand('MISSING', 'X', 1.0));
    }
}
