<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Console;

use App\Shared\Infrastructure\Console\CheckSecretsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckSecretsCommandTest extends TestCase
{
    private const array STRONG_SECRETS = [
        'APP_SECRET'                 => 'app-secret-0123456789abcdef0123456789',
        'JWT_PASSPHRASE'             => 'jwt-passphrase-strong',
        'GATE_PASSWORD'              => 'gate-password-strong',
        'MONITOR_PASSWORD'           => 'monitor-password-strong',
        'MERCURE_PUBLISHER_JWT_KEY'  => 'mercure-publisher-0123456789abcdef01',
        'MERCURE_SUBSCRIBER_JWT_KEY' => 'mercure-subscriber-0123456789abcdef0',
        'SCAN_TOKEN_SECRET'          => 'scan-token-secret-0123456789abcdef01',
    ];

    public function testSucceedsWhenEverySecretIsSet(): void
    {
        $tester = new CommandTester(new CheckSecretsCommand(self::STRONG_SECRETS));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Every secret is set', $tester->getDisplay());
    }

    public function testListsWeakSecretsByNameWithoutTheirValue(): void
    {
        $tester = new CommandTester(new CheckSecretsCommand([
            ...self::STRONG_SECRETS,
            'MONITOR_PASSWORD'  => 'CHANGEME',
            'SCAN_TOKEN_SECRET' => null,
        ]));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('MONITOR_PASSWORD', $display);
        $this->assertStringContainsString('SCAN_TOKEN_SECRET', $display);
        $this->assertStringNotContainsString('GATE_PASSWORD', $display);
        $this->assertStringNotContainsString('CHANGEME', $display);
    }
}
