<?php

declare(strict_types=1);

namespace App\Manga\Application\SearchExternal;

use App\Manga\Domain\ExternalApiClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class SearchExternalMangaHandler
{
    public function __construct(private ExternalApiClientInterface $client)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function __invoke(SearchExternalMangaQuery $query): array
    {
        return array_map(
            static fn ($dto) => [
                'externalId' => $dto->externalId,
                'title' => $dto->title,
                'edition' => $dto->edition,
                'author' => $dto->author,
                'summary' => $dto->summary,
                'coverUrl' => $dto->coverUrl,
                'genre' => $dto->genre,
                'language' => $dto->language,
                'totalVolumes' => $dto->totalVolumes,
            ],
            $this->client->searchByTitle($query->query, $query->type, $query->page),
        );
    }
}
