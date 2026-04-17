<?php

declare(strict_types=1);

namespace App\Tests\Unit\PriceCode\Domain\Exception;

use App\PriceCode\Domain\Exception\PriceCodeAlreadyExistsException;
use PHPUnit\Framework\TestCase;

class PriceCodeAlreadyExistsExceptionTest extends TestCase
{
    public function testMessage(): void
    {
        $e = new PriceCodeAlreadyExistsException('POCHE');

        $this->assertSame('Price code "POCHE" already exists.', $e->getMessage());
    }

    public function testHttpStatusCodeIs409(): void
    {
        $e = new PriceCodeAlreadyExistsException('POCHE');

        $this->assertSame(409, $e->getHttpStatusCode());
    }
}
