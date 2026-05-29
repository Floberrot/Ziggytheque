<?php

declare(strict_types=1);

namespace App\Manga\Application\DiscoverEditions;

use App\Manga\Domain\EditionDiscoveryInterface;
use App\Manga\Domain\ExternalEditionDto;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class DiscoverEditionsHandler
{
    public function __construct(private EditionDiscoveryInterface $editionDiscovery)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function __invoke(DiscoverEditionsQuery $query): array
    {
        $editions = $this->editionDiscovery->discoverEditions($query->title, $query->country);

        return array_map(
            fn (ExternalEditionDto $edition) => $edition->toArray(),
            $editions,
        );
    }
}
