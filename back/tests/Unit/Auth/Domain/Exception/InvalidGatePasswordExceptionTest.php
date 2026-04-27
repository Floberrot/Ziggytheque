<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Domain\Exception;

use App\Auth\Domain\Exception\InvalidGatePasswordException;
use PHPUnit\Framework\TestCase;

final class InvalidGatePasswordExceptionTest extends TestCase
{
    public function testMessage(): void
    {
        $e = new InvalidGatePasswordException();
        $this->assertSame('Invalid gate password.', $e->getMessage());
    }

    public function testHttpStatusCode(): void
    {
        $e = new InvalidGatePasswordException();
        $this->assertSame(401, $e->getHttpStatusCode());
    }
}
