<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Exception;

use App\Manga\Domain\Exception\ExternalApiUnavailableException;
use PHPUnit\Framework\TestCase;

class ExternalApiUnavailableExceptionTest extends TestCase
{
    public function testHttpStatusCodeIs503(): void
    {
        $e = new ExternalApiUnavailableException();

        $this->assertSame(503, $e->getHttpStatusCode());
    }

    public function testMessageDescribesUnavailability(): void
    {
        $e = new ExternalApiUnavailableException();

        $this->assertStringContainsString('unavailable', $e->getMessage());
    }
}
