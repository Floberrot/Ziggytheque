<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Listener;

use App\Collection\Shared\Event\AddFromCatalogueSucceededEvent;
use App\Collection\Shared\Event\ScanIsbnSucceededEvent;
use App\Manga\Application\Enrich\EnrichMangaMessage;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

/** A series just created from the catalogue gets its genre / summary in the background. */
#[AsEventListener(event: AddFromCatalogueSucceededEvent::class, method: 'onAddedFromCatalogue')]
#[AsEventListener(event: ScanIsbnSucceededEvent::class, method: 'onScanned')]
final readonly class EnrichNewSeriesListener
{
    public function __construct(private MessageBusInterface $messageBus)
    {
    }

    public function onAddedFromCatalogue(AddFromCatalogueSucceededEvent $event): void
    {
        if ($event->seriesCreated) {
            $this->messageBus->dispatch(new EnrichMangaMessage($event->mangaId));
        }
    }

    public function onScanned(ScanIsbnSucceededEvent $event): void
    {
        if ($event->seriesCreated) {
            $this->messageBus->dispatch(new EnrichMangaMessage($event->mangaId));
        }
    }
}
