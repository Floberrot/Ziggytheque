<?php

declare(strict_types=1);

namespace App\Manga\Application\SearchVolumeExternal;

use App\Manga\Domain\ExternalApiClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class SearchVolumeExternalHandler
{
    public function __construct(
        private ExternalApiClientInterface $externalApi,
        private LoggerInterface $logger,
    ) {
    }

    /** @return array{source: string, results: array<int, array<string, mixed>>} */
    public function __invoke(SearchVolumeExternalQuery $query): array
    {
        $this->logger->info('SearchVolumeExternalHandler: processing query', [
            'search' => $query->search,
            'page' => $query->page,
        ]);

        try {
            $results = $this->externalApi->searchByTitle($query->search, 'manga', $query->page);

            $this->logger->info('SearchVolumeExternalHandler: success', [
                'count' => count($results),
                'search' => $query->search,
            ]);

            return [
                'source' => 'google',
                'results' => array_map(static fn ($dto) => [
                    'externalId' => $dto->externalId,
                    'coverUrl'   => $dto->coverUrl,
                    'title'      => $dto->title,
                ], $results),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('SearchVolumeExternalHandler: failed', [
                'error' => $e->getMessage(),
                'search' => $query->search,
            ]);
            throw $e;
        }
    }
}
