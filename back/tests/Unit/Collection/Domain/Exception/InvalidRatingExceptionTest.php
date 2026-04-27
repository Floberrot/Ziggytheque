<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Domain\Exception;

use App\Collection\Domain\Exception\InvalidRatingException;
use PHPUnit\Framework\TestCase;

final class InvalidRatingExceptionTest extends TestCase
{
    public function testMessage(): void
    {
        $e = new InvalidRatingException(15);
        $this->assertSame('Rating must be between 0 and 10 (half-points × 2), got 15.', $e->getMessage());
    }

    public function testHttpStatusCode(): void
    {
        $e = new InvalidRatingException(0);
        $this->assertSame(422, $e->getHttpStatusCode());
    }
}
