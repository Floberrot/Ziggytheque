<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Domain\Exception;

use App\Auth\Domain\Exception\GateDisabledException;
use PHPUnit\Framework\TestCase;

final class GateDisabledExceptionTest extends TestCase
{
    public function testExplainsWhatToSetAndAnswers503(): void
    {
        $exception = new GateDisabledException();

        $this->assertStringContainsString('GATE_PASSWORD', $exception->getMessage());
        $this->assertSame(503, $exception->getHttpStatusCode());
    }
}
