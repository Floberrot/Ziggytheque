<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Console;

use App\Auth\Application\BootstrapAdmin\BootstrapAdminCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:bootstrap-admin',
    description: 'Create the admin user and backfill all existing data to that admin.',
)]
final class BootstrapAdminConsoleCommand extends Command
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Admin email address')
            ->addArgument('password', InputArgument::REQUIRED, 'Admin password')
            ->addOption('display-name', null, InputOption::VALUE_OPTIONAL, 'Admin display name', 'Admin')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force creation even if an admin already exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $email */
        $email = $input->getArgument('email');
        /** @var string $password */
        $password = $input->getArgument('password');
        /** @var string $displayName */
        $displayName = (string) $input->getOption('display-name');
        $force       = (bool) $input->getOption('force');

        try {
            $report = $this->commandBus->dispatch(new BootstrapAdminCommand(
                email: $email,
                password: $password,
                displayName: $displayName,
                force: $force,
            ));

            $io->success(sprintf('Admin user created: %s', $email));
            $io->table(
                ['Table', 'Rows backfilled'],
                [
                    ['collection_entries', $report->collectionEntries],
                    ['notifications', $report->notifications],
                    ['articles', $report->articles],
                    ['activity_logs', $report->activityLogs],
                    ['TOTAL', $report->total()],
                ],
            );
        } catch (Exception $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
