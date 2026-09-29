<?php

declare(strict_types=1);

namespace App\Collection\Application\GetCatalogueEdition;

use App\Collection\Domain\Service\CatalogueCollectionMatcher;
use App\Manga\Domain\EditionIdentity;
use App\Manga\Domain\Service\CatalogueSearch;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetCatalogueEditionHandler
{
    public function __construct(
        private CatalogueSearch $catalogueSearch,
        private CatalogueCollectionMatcher $matcher,
    ) {
    }

    /** @return array<string, mixed> */
    public function __invoke(GetCatalogueEditionQuery $query): array
    {
        $identity = new EditionIdentity($query->workTitle, $query->publisher, $query->specialEdition);
        $edition  = $this->catalogueSearch->edition($identity);

        if ($edition === null) {
            throw new NotFoundException('CatalogueEdition', $query->workTitle);
        }

        return $this->matcher->describe([$edition])[0];
    }
}
