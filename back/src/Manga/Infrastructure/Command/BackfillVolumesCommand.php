<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Command;

use App\Manga\Domain\CoverBatchResult;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Service\CoverBatchResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:manga:backfill-volumes',
    description: 'Backfill ISBN, coverUrl and spineUrl for all volumes via MangaDex / Open Library / Google Books.',
)]
final class BackfillVolumesCommand extends Command
{
    private const int BATCH_SIZE = 50;

    public function __construct(
        private readonly MangaRepositoryInterface $mangaRepository,
        private readonly CoverBatchResolver $resolver,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('manga-id', null, InputOption::VALUE_REQUIRED, 'Restrict to a single manga UUID')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing coverUrl/spineUrl/isbn')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be updated without persisting')
            ->addOption('sleep-ms', null, InputOption::VALUE_REQUIRED, 'Pause between mangas in milliseconds', '200')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Process at most N mangas');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run');
        $sleepMs = (int) $input->getOption('sleep-ms');
        $singleMangaId = $input->getOption('manga-id');
        $maxMangas = $input->getOption('limit') !== null ? (int) $input->getOption('limit') : null;

        if ($singleMangaId !== null) {
            return $this->processSingleManga($io, (string) $singleMangaId, $force, $dryRun);
        }

        return $this->processAllMangas($io, $output, $force, $dryRun, $sleepMs, $maxMangas);
    }

    private function processSingleManga(SymfonyStyle $io, string $mangaId, bool $force, bool $dryRun): int
    {
        $manga = $this->mangaRepository->findById($mangaId);
        if ($manga === null) {
            $io->error(sprintf('Manga with ID "%s" not found.', $mangaId));
            return Command::FAILURE;
        }

        try {
            /** @var CoverBatchResult $result */
            $result = $this->resolver->resolveAll($manga, $force, null);

            if (!$dryRun) {
                $this->mangaRepository->save($manga);
            }

            $io->success(sprintf(
                'Done. Updated: %d | Skipped: %d | Failed: %d',
                $result->updated,
                $result->skipped,
                $result->failed,
            ));

            return Command::SUCCESS;
        } catch (Throwable $exception) {
            $io->error(sprintf('Failed: %s', $exception->getMessage()));
            $this->logger->error('BackfillVolumesCommand: single manga failed', [
                'manga_id' => $mangaId,
                'error' => $exception->getMessage(),
            ]);
            return Command::FAILURE;
        }
    }

    private function processAllMangas(
        SymfonyStyle $io,
        OutputInterface $output,
        bool $force,
        bool $dryRun,
        int $sleepMs,
        ?int $maxMangas,
    ): int {
        $totalCount = $maxMangas ?? $this->mangaRepository->countAll();
        $io->info(sprintf('Backfilling %d manga series%s...', $totalCount, $dryRun ? ' (dry-run)' : ''));

        $progressBar = new ProgressBar($output, $totalCount);
        $progressBar->start();

        $totalUpdated = 0;
        $totalFailed = 0;
        $totalSkipped = 0;
        $mangaErrors = 0;
        $processed = 0;
        $offset = 0;
        $hasError = false;

        while ($processed < $totalCount) {
            $batchLimit = min(self::BATCH_SIZE, $totalCount - $processed);
            $mangas = $this->mangaRepository->findAllPaginated($offset, $batchLimit);

            if (empty($mangas)) {
                break;
            }

            foreach ($mangas as $manga) {
                try {
                    $result = $this->resolver->resolveAll($manga, $force, null);

                    if (!$dryRun) {
                        $this->mangaRepository->save($manga);
                    }

                    $totalUpdated += $result->updated;
                    $totalFailed += $result->failed;
                    $totalSkipped += $result->skipped;
                } catch (Throwable $exception) {
                    $mangaErrors++;
                    $hasError = true;
                    $this->logger->error('BackfillVolumesCommand: manga failed', [
                        'manga_id' => $manga->id,
                        'error' => $exception->getMessage(),
                    ]);
                }

                $progressBar->advance();
                $processed++;

                if ($processed >= $totalCount) {
                    break;
                }

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }

            $offset += count($mangas);
        }

        $progressBar->finish();
        $output->writeln('');

        $summary = implode("\n", [
            'Done.',
            sprintf('  - Series processed:  %d', $processed),
            sprintf('  - Volumes updated:   %d', $totalUpdated),
            sprintf('  - Volumes skipped:   %d  (already had cover)', $totalSkipped),
            sprintf('  - Volumes failed:    %d  (no source returned a match)', $totalFailed),
            sprintf('  - Network errors:    %d', $mangaErrors),
        ]);
        $io->success($summary);

        if (!$force && ($totalUpdated > 0 || $totalFailed > 0)) {
            $io->note('Run again with --force to overwrite existing covers.');
        }

        return $hasError ? Command::FAILURE : Command::SUCCESS;
    }
}
