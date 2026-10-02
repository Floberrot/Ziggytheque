<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Security;

use App\Shared\Domain\Security\SecretStrength;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecretStrengthTest extends TestCase
{
    /** @return iterable<string, array{string, int}> */
    public static function weakSecrets(): iterable
    {
        yield 'empty' => ['', SecretStrength::MIN_PASSWORD_LENGTH];
        yield 'blank' => ['     ', SecretStrength::MIN_PASSWORD_LENGTH];
        yield 'too short' => ['short-pass', SecretStrength::MIN_PASSWORD_LENGTH];
        yield 'deploy placeholder' => ['CHANGEME', 1];
        yield 'old committed gate default' => ['ziggy123', 1];
        yield 'old committed monitor default' => ['monitor', 1];
        yield 'old committed mercure default' => ['!ChangeThisMercurePublisherSecret32c!', SecretStrength::MIN_KEY_LENGTH];
        yield 'change-me spelling' => ['change-me-please-before-production', SecretStrength::MIN_KEY_LENGTH];
    }

    #[DataProvider('weakSecrets')]
    public function testFlagsWeakSecrets(string $secret, int $minimumLength): void
    {
        $this->assertTrue(SecretStrength::isWeak($secret, $minimumLength));
    }

    public function testAcceptsARealSecret(): void
    {
        $this->assertFalse(SecretStrength::isWeak('k3y-9f2c1b7e4d0a6c8e5b3f1d9a7c5e3b1d', SecretStrength::MIN_KEY_LENGTH));
        $this->assertFalse(SecretStrength::isWeak('a-long-monitor-password', SecretStrength::MIN_PASSWORD_LENGTH));
    }
}
