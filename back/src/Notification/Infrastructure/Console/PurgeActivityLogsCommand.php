<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Console;

use App\Notification\Domain\Service\ActivityLogPurger;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:activity-log:purge',
    description: 'Delete activity logs older than the retention window (default 90 days).',
)]
final class PurgeActivityLogsCommand extends Command
{
    public function __construct(private readonly ActivityLogPurger $purger)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            null,
            InputOption::VALUE_REQUIRED,
            'Retention window in days',
            (string) ActivityLogPurger::DEFAULT_RETENTION_DAYS,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');

        try {
            $deletedCount = $this->purger->purgeOlderThanDays($days);
        } catch (InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        $io->success(sprintf('Deleted %d activity log(s) older than %d day(s).', $deletedCount, $days));

        return Command::SUCCESS;
    }
}
