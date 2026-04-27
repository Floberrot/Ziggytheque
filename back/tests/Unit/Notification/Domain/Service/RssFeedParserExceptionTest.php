<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain\Service;

use App\Notification\Domain\Service\RssFeedParserException;
use PHPUnit\Framework\TestCase;

final class RssFeedParserExceptionTest extends TestCase
{
    public function testHttpError(): void
    {
        $e = RssFeedParserException::httpError(503);
        $this->assertSame('HTTP 503', $e->getMessage());
    }

    public function testInvalidXml(): void
    {
        $e = RssFeedParserException::invalidXml('https://example.com/feed');
        $this->assertSame('Invalid XML from https://example.com/feed', $e->getMessage());
    }
}
