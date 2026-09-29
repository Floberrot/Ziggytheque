<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\GetCatalogueEdition;

use App\Collection\Application\GetCatalogueEdition\GetCatalogueEditionHandler;
use App\Collection\Application\GetCatalogueEdition\GetCatalogueEditionQuery;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Unit\Collection\Application\CatalogueHandlerTestCase;

final class GetCatalogueEditionHandlerTest extends CatalogueHandlerTestCase
{
    private function handler(): GetCatalogueEditionHandler
    {
        return new GetCatalogueEditionHandler($this->catalogueSearch, $this->matcher);
    }

    public function testReturnsTheSeriesWithEveryTome(): void
    {
        $edition = ($this->handler())(new GetCatalogueEditionQuery('Berserk', 'Glénat', 'Prestige'));

        $this->assertSame('Prestige', $edition['specialEdition']);
        $this->assertCount(3, $edition['volumes']);
        $this->assertNull($edition['collection']);
    }

    public function testUnknownSeriesIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        ($this->handler())(new GetCatalogueEditionQuery('Berserk', 'Kana', null));
    }
}
