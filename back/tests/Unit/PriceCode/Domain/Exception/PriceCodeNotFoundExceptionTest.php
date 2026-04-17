<?php

declare(strict_types=1);

namespace App\Tests\Unit\PriceCode\Domain\Exception;

use App\PriceCode\Domain\Exception\PriceCodeNotFoundException;
use PHPUnit\Framework\TestCase;

class PriceCodeNotFoundExceptionTest extends TestCase
{
    public function testMessage(): void
    {
        $e = new PriceCodeNotFoundException('POCHE');

        $this->assertSame('Price code "POCHE" not found.', $e->getMessage());
    }

    public function testHttpStatusCodeIs404(): void
    {
        $e = new PriceCodeNotFoundException('POCHE');

        $this->assertSame(404, $e->getHttpStatusCode());
    }
}
