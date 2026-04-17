<?php

declare(strict_types=1);

namespace App\Tests\Unit\PriceCode\Application\Create;

use App\PriceCode\Application\Create\CreatePriceCodeCommand;
use App\PriceCode\Application\Create\CreatePriceCodeHandler;
use App\PriceCode\Domain\Exception\PriceCodeAlreadyExistsException;
use App\PriceCode\Domain\PriceCode;
use App\PriceCode\Domain\PriceCodeRepositoryInterface;
use PHPUnit\Framework\TestCase;

class CreatePriceCodeHandlerTest extends TestCase
{
    public function testCreatesAndSavesPriceCode(): void
    {
        $repository = $this->createMock(PriceCodeRepositoryInterface::class);
        $repository->method('findByCode')->willReturn(null);
        $repository->expects($this->once())->method('save')->with($this->isInstanceOf(PriceCode::class));

        $handler = new CreatePriceCodeHandler($repository);
        $handler(new CreatePriceCodeCommand('POCHE', 'Poche', 6.99));
    }

    public function testThrowsIfCodeAlreadyExists(): void
    {
        $existing = new PriceCode('POCHE', 'Poche', 6.99);
        $repository = $this->createMock(PriceCodeRepositoryInterface::class);
        $repository->method('findByCode')->willReturn($existing);
        $repository->expects($this->never())->method('save');

        $handler = new CreatePriceCodeHandler($repository);

        $this->expectException(PriceCodeAlreadyExistsException::class);
        $handler(new CreatePriceCodeCommand('POCHE', 'Poche', 6.99));
    }
}
