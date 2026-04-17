<?php

declare(strict_types=1);

namespace App\Tests\Unit\PriceCode\Application\List;

use App\PriceCode\Application\List\ListPriceCodesHandler;
use App\PriceCode\Application\List\ListPriceCodesQuery;
use App\PriceCode\Domain\PriceCode;
use App\PriceCode\Domain\PriceCodeRepositoryInterface;
use PHPUnit\Framework\TestCase;

class ListPriceCodesHandlerTest extends TestCase
{
    public function testReturnsMappedArrays(): void
    {
        $pc = new PriceCode('POCHE', 'Poche', 6.99);
        $repository = $this->createMock(PriceCodeRepositoryInterface::class);
        $repository->method('findAll')->willReturn([$pc]);

        $handler = new ListPriceCodesHandler($repository);
        $result = $handler(new ListPriceCodesQuery());

        $this->assertCount(1, $result);
        $this->assertSame('POCHE', $result[0]['code']);
    }

    public function testReturnsEmptyArrayWhenNoEntries(): void
    {
        $repository = $this->createMock(PriceCodeRepositoryInterface::class);
        $repository->method('findAll')->willReturn([]);

        $handler = new ListPriceCodesHandler($repository);
        $result = $handler(new ListPriceCodesQuery());

        $this->assertSame([], $result);
    }
}
