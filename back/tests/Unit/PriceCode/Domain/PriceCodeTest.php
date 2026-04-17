<?php

declare(strict_types=1);

namespace App\Tests\Unit\PriceCode\Domain;

use App\PriceCode\Domain\PriceCode;
use PHPUnit\Framework\TestCase;

class PriceCodeTest extends TestCase
{
    public function testConstructorSetsFields(): void
    {
        $pc = new PriceCode('POCHE', 'Poche standard', 6.99);

        $this->assertSame('POCHE', $pc->code);
        $this->assertSame('Poche standard', $pc->label);
        $this->assertSame(6.99, $pc->value);
        $this->assertInstanceOf(\DateTimeImmutable::class, $pc->createdAt);
    }

    public function testUpdateChangesLabelAndValue(): void
    {
        $pc = new PriceCode('POCHE', 'Old label', 5.0);

        $pc->update('New label', 7.5);

        $this->assertSame('New label', $pc->label);
        $this->assertSame(7.5, $pc->value);
        $this->assertSame('POCHE', $pc->code);
    }

    public function testToArrayReturnsExpectedKeys(): void
    {
        $pc = new PriceCode('POCHE', 'Poche', 6.99);
        $arr = $pc->toArray();

        $this->assertArrayHasKey('code', $arr);
        $this->assertArrayHasKey('label', $arr);
        $this->assertArrayHasKey('value', $arr);
        $this->assertArrayHasKey('createdAt', $arr);
        $this->assertSame('POCHE', $arr['code']);
        $this->assertSame('Poche', $arr['label']);
        $this->assertSame(6.99, $arr['value']);
    }
}
