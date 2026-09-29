<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Infrastructure\Listener;

use App\Collection\Infrastructure\Listener\EnrichNewSeriesListener;
use App\Collection\Shared\Event\AddFromCatalogueSucceededEvent;
use App\Collection\Shared\Event\ScanIsbnSucceededEvent;
use App\Manga\Application\Enrich\EnrichMangaMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class EnrichNewSeriesListenerTest extends TestCase
{
    /** @var list<object> */
    private array $dispatched = [];

    private function listener(): EnrichNewSeriesListener
    {
        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->dispatched[] = $message;

            return new Envelope($message);
        });

        return new EnrichNewSeriesListener($messageBus);
    }

    public function testANewSeriesIsSentToEnrichment(): void
    {
        $listener = $this->listener();

        $listener->onAddedFromCatalogue(new AddFromCatalogueSucceededEvent('c1', 'ce1', 'm1', 'Berserk', true, [1]));
        $listener->onScanned(new ScanIsbnSucceededEvent('c2', '9782723425483', 'ce2', 'm2', 'Naruto', 1, false, true));

        $this->assertEquals([new EnrichMangaMessage('m1'), new EnrichMangaMessage('m2')], $this->dispatched);
    }

    public function testAnExistingSeriesIsLeftAlone(): void
    {
        $listener = $this->listener();

        $listener->onAddedFromCatalogue(new AddFromCatalogueSucceededEvent('c1', 'ce1', 'm1', 'Berserk', false, [1]));
        $listener->onScanned(new ScanIsbnSucceededEvent('c2', '9782723425483', 'ce2', 'm2', 'Naruto', 1, true, false));

        $this->assertSame([], $this->dispatched);
    }
}
