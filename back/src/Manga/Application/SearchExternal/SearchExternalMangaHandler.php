<?php

declare(strict_types=1);

namespace App\Manga\Application\SearchExternal;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class SearchExternalMangaHandler
{
    public function __construct(
        private ExternalApiClientInterface $client,
        private ContainerInterface $providerLocator,
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function __invoke(SearchExternalMangaQuery $query): array
    {
        $client = $this->resolveClient($query->provider);

        return array_map(
            static fn (ExternalMangaDto $dto) => [
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
            $client->searchByTitle($query->query, $query->type, $query->page),
        );
    }

    /** Resolve the requested provider by key, falling back to the default client. */
    private function resolveClient(string $providerKey): ExternalApiClientInterface
    {
        if ($providerKey !== '' && $this->providerLocator->has($providerKey)) {
            return $this->providerLocator->get($providerKey);
        }

        return $this->client;
    }
}
