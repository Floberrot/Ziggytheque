<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\ScanIsbn;

use App\Collection\Application\ScanIsbn\ScanIsbnCommand;
use App\Collection\Application\ScanIsbn\ScanIsbnHandler;
use App\Collection\Shared\Event\ScanIsbnFailedEvent;
use App\Collection\Shared\Event\ScanIsbnStartedEvent;
use App\Collection\Shared\Event\ScanIsbnSucceededEvent;
use App\Manga\Domain\Exception\InvalidIsbnException;
use App\Manga\Domain\Exception\IsbnNotInCatalogueException;
use App\Tests\Unit\Collection\Application\CatalogueHandlerTestCase;

final class ScanIsbnHandlerTest extends CatalogueHandlerTestCase
{
    private function handler(): ScanIsbnHandler
    {
        return new ScanIsbnHandler(
            $this->catalogueSearch,
            $this->registrar,
            $this->userRepository,
            $this->currentUserProvider,
            $this->eventBus,
        );
    }

    public function testAScanAddsTheTomeAndItsSeries(): void
    {
        $result = ($this->handler())(new ScanIsbnCommand('978-2-344-03608-2'));

        $this->assertSame(2, $result['volumeNumber']);
        $this->assertFalse($result['alreadyOwned']);
        $this->assertSame('Prestige', $result['edition']['specialEdition']);
        $this->assertSame(3, $result['edition']['volumeCount']);
        $this->assertTrue($result['registration']['seriesCreated']);
        $this->assertSame([2], $result['registration']['addedNumbers']);

        $this->assertSame([ScanIsbnStartedEvent::class, ScanIsbnSucceededEvent::class], $this->publishedEventClasses());
        $succeeded = $this->publishedEvents[1];
        $this->assertInstanceOf(ScanIsbnSucceededEvent::class, $succeeded);
        $this->assertSame(2, $succeeded->volumeNumber);
        $this->assertTrue($succeeded->seriesCreated);
        $this->assertSame($result['registration']['mangaId'], $succeeded->mangaId);
    }

    public function testScanningAnOwnedTomeAgainReportsItAsAlreadyOwned(): void
    {
        ($this->handler())(new ScanIsbnCommand('9782344036082'));

        $result = ($this->handler())(new ScanIsbnCommand('9782344036082'));

        $this->assertTrue($result['alreadyOwned']);
        $this->assertFalse($result['registration']['seriesCreated']);
    }

    public function testAnUnknownIsbnPublishesAFailedEvent(): void
    {
        $this->expectException(IsbnNotInCatalogueException::class);

        try {
            ($this->handler())(new ScanIsbnCommand('9782811645632'));
        } finally {
            $this->assertSame([ScanIsbnStartedEvent::class, ScanIsbnFailedEvent::class], $this->publishedEventClasses());
        }
    }

    public function testAnInvalidIsbnIsRejected(): void
    {
        $this->expectException(InvalidIsbnException::class);

        ($this->handler())(new ScanIsbnCommand('not-an-isbn'));
    }
}
