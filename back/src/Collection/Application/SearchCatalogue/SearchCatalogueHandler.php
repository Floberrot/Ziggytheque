<?php

declare(strict_types=1);

namespace App\Collection\Application\SearchCatalogue;

use App\Collection\Domain\Service\CatalogueCollectionMatcher;
use App\Manga\Domain\Service\CatalogueSearch;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class SearchCatalogueHandler
{
    public function __construct(
        private CatalogueSearch $catalogueSearch,
        private CatalogueCollectionMatcher $matcher,
    ) {
    }

    /** @return array{query: string, requestedVolume: int|null, editions: list<array<string, mixed>>} */
    public function __invoke(SearchCatalogueQuery $query): array
    {
        $result = $this->catalogueSearch->search($query->query, $query->mode);

        return [
            'query'           => $result->searchedText,
            'requestedVolume' => $result->requestedVolume,
            'editions'        => $this->matcher->describe($result->editions),
        ];
    }
}
