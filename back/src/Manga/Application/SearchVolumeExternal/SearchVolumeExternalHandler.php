<?php

declare(strict_types=1);

namespace App\Manga\Application\SearchVolumeExternal;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Uses Google Books directly (not the series-search interface) to find
 * individual volume covers for a specific tome of an oeuvre.
 */
#[AsMessageHandler(bus: 'query.bus')]
final readonly class SearchVolumeExternalHandler
{
    public function __construct(private ExternalApiClientInterface $googleBooks)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function __invoke(SearchVolumeExternalQuery $query): array
    {
        $results = $this->googleBooks->searchByTitle($query->search, page: $query->page);

        return array_map(static fn (ExternalMangaDto $dto) => [
            'externalId' => $dto->externalId,
            'title'      => $dto->title,
            'edition'    => $dto->edition,
            'author'     => $dto->author,
            'coverUrl'   => $dto->coverUrl,
            'language'   => $dto->language,
            'totalVolumes' => $dto->totalVolumes,
        ], $results);
    }
}
