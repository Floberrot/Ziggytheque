<?php

declare(strict_types=1);

namespace App\Tests\Unit\PriceCode\Application\Delete;

use App\PriceCode\Application\Delete\DeletePriceCodeCommand;
use App\PriceCode\Application\Delete\DeletePriceCodeHandler;
use App\PriceCode\Domain\Exception\PriceCodeNotFoundException;
use App\PriceCode\Domain\PriceCode;
use App\PriceCode\Domain\PriceCodeRepositoryInterface;
use PHPUnit\Framework\TestCase;

class DeletePriceCodeHandlerTest extends TestCase
{
    public function testDeletesExistingPriceCode(): void
    {
        $pc = new PriceCode('POCHE', 'Poche', 6.99);
        $repository = $this->createMock(PriceCodeRepositoryInterface::class);
        $repository->method('findByCode')->with('POCHE')->willReturn($pc);
        $repository->expects($this->once())->method('delete')->with($pc);

        $handler = new DeletePriceCodeHandler($repository);
        $handler(new DeletePriceCodeCommand('POCHE'));
    }

    public function testThrowsWhenNotFound(): void
    {
        $repository = $this->createMock(PriceCodeRepositoryInterface::class);
        $repository->method('findByCode')->willReturn(null);

        $handler = new DeletePriceCodeHandler($repository);

        $this->expectException(PriceCodeNotFoundException::class);
        $handler(new DeletePriceCodeCommand('MISSING'));
    }
}
