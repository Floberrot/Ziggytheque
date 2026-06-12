<?php

declare(strict_types=1);

namespace App\Manga\Application\DiscoverEditions;

use App\Manga\Domain\EditionDiscoveryCacheInterface;
use App\Manga\Domain\EditionProviderInterface;
use App\Manga\Domain\ExternalEditionDto;
use App\Manga\Domain\Service\EditionCoverBackfiller;
use App\Manga\Domain\Service\EditionGrouper;
use App\Manga\Domain\WorkTitleResolverInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class DiscoverEditionsHandler
{
    public function __construct(
        private EditionProviderInterface $provider,
        private EditionGrouper $grouper,
        private EditionCoverBackfiller $coverBackfiller,
        private WorkTitleResolverInterface $titleResolver,
        private EditionDiscoveryCacheInterface $cache,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(DiscoverEditionsQuery $query): array
    {
        // A repeated search is served from cache — no fan-out to every catalogue.
        $cached = $this->cache->get($query->query, $query->author, $query->language);
        if ($cached !== null) {
            return $cached;
        }

        // Searching a foreign catalogue needs the work's title in that language
        // (進撃の巨人, "Attack on Titan"…), not the French query the user typed.
        $searchTitle = $this->titleResolver->resolve($query->query, $query->language) ?? $query->query;

        $editions = $this->provider->findEditions($searchTitle, $query->author, $query->language);
        $grouped  = $this->grouper->group($editions);
        $enriched = $this->coverBackfiller->backfill($grouped);

        $result = array_map(
            static fn (ExternalEditionDto $edition) => $edition->toArray(),
            $enriched,
        );

        $this->cache->put($query->query, $query->author, $query->language, $result);

        return $result;
    }
}
