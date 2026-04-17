<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Domain\Exception;

use App\Auth\Domain\Exception\InvalidGatePasswordException;
use PHPUnit\Framework\TestCase;

class InvalidGatePasswordExceptionTest extends TestCase
{
    public function testMessage(): void
    {
        $e = new InvalidGatePasswordException();

        $this->assertSame('Invalid gate password.', $e->getMessage());
    }

    public function testHttpStatusCodeIs401(): void
    {
        $e = new InvalidGatePasswordException();

        $this->assertSame(401, $e->getHttpStatusCode());
    }
}
