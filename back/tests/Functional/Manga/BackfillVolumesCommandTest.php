<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manga;

use App\Manga\Infrastructure\Command\BackfillVolumesCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class BackfillVolumesCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('app:manga:backfill-volumes');
        $this->commandTester = new CommandTester($command);
    }

    public function testExitsSuccessfully(): void
    {
        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Series processed:', $this->commandTester->getDisplay());
    }

    public function testDryRunExitsSuccessfully(): void
    {
        $exitCode = $this->commandTester->execute(['--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Series processed:', $this->commandTester->getDisplay());
    }

    public function testRejectsUnknownMangaId(): void
    {
        $exitCode = $this->commandTester->execute(['--manga-id' => 'nonexistent-uuid']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('not found', $this->commandTester->getDisplay());
    }
}
