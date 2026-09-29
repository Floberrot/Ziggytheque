<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\SearchCatalogue;

use App\Collection\Application\AddFromCatalogue\AddFromCatalogueCommand;
use App\Collection\Application\AddFromCatalogue\AddFromCatalogueHandler;
use App\Collection\Application\SearchCatalogue\SearchCatalogueHandler;
use App\Collection\Application\SearchCatalogue\SearchCatalogueQuery;
use App\Manga\Domain\Catalogue\CatalogueEdition;
use App\Manga\Domain\Catalogue\CatalogueSearchModeEnum;
use App\Tests\Unit\Collection\Application\CatalogueHandlerTestCase;

final class SearchCatalogueHandlerTest extends CatalogueHandlerTestCase
{
    public function testReturnsTheEditionsWithTheCollectionStatus(): void
    {
        (new AddFromCatalogueHandler($this->registrar, $this->userRepository, $this->currentUserProvider, $this->eventBus))(
            new AddFromCatalogueCommand(new CatalogueEdition('Berserk', 'Glénat', 'Prestige', null, null, 3, []), [2]),
        );

        $result = (new SearchCatalogueHandler($this->catalogueSearch, $this->matcher))(
            new SearchCatalogueQuery('berserk 2', CatalogueSearchModeEnum::Title),
        );

        $this->assertSame('berserk', $result['query']);
        $this->assertSame(2, $result['requestedVolume']);
        $this->assertCount(1, $result['editions']);
        $this->assertSame([2], $result['editions'][0]['collection']['ownedNumbers']);
    }
}
