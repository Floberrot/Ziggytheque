<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain\Exception;

use App\Notification\Domain\Exception\TestNotificationConfigurationException;
use App\Shared\Domain\Exception\DomainException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TestNotificationConfigurationExceptionTest extends TestCase
{
    public function testCarriesTheMessageShownToTheUser(): void
    {
        $exception = new TestNotificationConfigurationException('No Discord webhook configured.');

        $this->assertSame('No Discord webhook configured.', $exception->getMessage());
        $this->assertInstanceOf(RuntimeException::class, $exception);
        // Never mapped to an HTTP answer: the handler turns it into a notification.
        $this->assertNotInstanceOf(DomainException::class, $exception);
    }
}
