<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\Exception\RateLimitExceededException;
use PHPUnit\Framework\TestCase;

final class RateLimitExceededExceptionTest extends TestCase
{
    public function testHasHttp429Status(): void
    {
        $this->assertSame(429, (new RateLimitExceededException('slow down'))->getHttpStatusCode());
    }

    public function testIsADomainExceptionCarryingItsMessage(): void
    {
        $exception = new RateLimitExceededException('slow down');

        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertSame('slow down', $exception->getMessage());
    }
}
