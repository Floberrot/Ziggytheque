<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure\Scan;

use App\Manga\Domain\Exception\InvalidScanTokenException;
use App\Manga\Infrastructure\Scan\JwtScanTokenIssuer;
use PHPUnit\Framework\TestCase;

final class JwtScanTokenIssuerTest extends TestCase
{
    private const string SECRET = 'test-scan-token-secret-32-characters!!';

    public function testIssueAndVerifyReturnsSameSessionId(): void
    {
        $issuer = new JwtScanTokenIssuer(self::SECRET);
        $sessionId = 'my-session-id-123';

        $token = $issuer->issue($sessionId, ttlSeconds: 600);
        $verified = $issuer->verify($token);

        $this->assertSame($sessionId, $verified);
    }

    public function testVerifyThrowsForWrongSecret(): void
    {
        $issuer = new JwtScanTokenIssuer(self::SECRET);
        $token = $issuer->issue('session-abc', ttlSeconds: 600);

        $wrongIssuer = new JwtScanTokenIssuer('completely-different-secret-32chars!!');

        $this->expectException(InvalidScanTokenException::class);
        $wrongIssuer->verify($token);
    }

    public function testVerifyThrowsForExpiredToken(): void
    {
        $issuer = new JwtScanTokenIssuer(self::SECRET);
        $token = $issuer->issue('session-expired', ttlSeconds: -1);

        $this->expectException(InvalidScanTokenException::class);
        $issuer->verify($token);
    }

    public function testVerifyThrowsForGarbageInput(): void
    {
        $issuer = new JwtScanTokenIssuer(self::SECRET);

        $this->expectException(InvalidScanTokenException::class);
        $issuer->verify('garbage-token-value');
    }
}
