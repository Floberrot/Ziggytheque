<?php

declare(strict_types=1);

namespace App\Manga\Application\SearchVolumeExternal;

use App\Manga\Infrastructure\ExternalApi\FallbackCoverApiClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class SearchVolumeExternalHandler
{
    public function __construct(private FallbackCoverApiClient $coverClient)
    {
    }

    /** @return array{source: string, results: array<int, array<string, mixed>>} */
    public function __invoke(SearchVolumeExternalQuery $query): array
    {
        $result = $this->coverClient->search($query->search);

        return [
            'source' => $result['source'],
            'results' => array_map(static fn ($dto) => [
                'externalId' => $dto->externalId,
                'coverUrl'   => $dto->coverUrl,
                'title'      => $dto->title,
            ], $result['results']),
        ];
    }
}
