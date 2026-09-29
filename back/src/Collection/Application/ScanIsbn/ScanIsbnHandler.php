<?php

declare(strict_types=1);

namespace App\Collection\Application\ScanIsbn;

use App\Auth\Domain\UserRepositoryInterface;
use App\Collection\Domain\Service\CatalogueEntryRegistrar;
use App\Collection\Shared\Event\ScanIsbnFailedEvent;
use App\Collection\Shared\Event\ScanIsbnStartedEvent;
use App\Collection\Shared\Event\ScanIsbnSucceededEvent;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\Service\CatalogueSearch;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Security\CurrentUserProviderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * One scan = one tome in the collection: the ISBN is placed in its series (created
 * with all its tomes when new) and that tome is marked as owned.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class ScanIsbnHandler
{
    public function __construct(
        private CatalogueSearch $catalogueSearch,
        private CatalogueEntryRegistrar $registrar,
        private UserRepositoryInterface $userRepository,
        private CurrentUserProviderInterface $currentUserProvider,
        private EventBusInterface $eventBus,
    ) {
    }

    /** @return array<string, mixed> */
    public function __invoke(ScanIsbnCommand $command): array
    {
        $started = new ScanIsbnStartedEvent(isbn: $command->isbn);
        $this->eventBus->publish($started);

        try {
            $identification = $this->catalogueSearch->identifyIsbn(Isbn::fromString($command->isbn));
            $registration   = $this->registrar->register(
                $identification->edition,
                [$identification->volume->number],
                $this->userRepository->findById($this->currentUserProvider->currentUserId()),
            );

            $this->eventBus->publish(new ScanIsbnSucceededEvent(
                correlationId: $started->correlationId,
                isbn: $command->isbn,
                collectionEntryId: $registration->collectionEntryId,
                mangaId: $registration->mangaId,
                workTitle: $identification->edition->workTitle,
                volumeNumber: $identification->volume->number,
                alreadyOwned: $registration->addedNothing(),
                seriesCreated: $registration->seriesCreated,
            ));

            return [
                'edition'      => $identification->edition->toArray(),
                'volumeNumber' => $identification->volume->number,
                'alreadyOwned' => $registration->addedNothing(),
                'registration' => $registration->toArray(),
            ];
        } catch (Throwable $exception) {
            $this->eventBus->publish(new ScanIsbnFailedEvent(
                correlationId: $started->correlationId,
                isbn: $command->isbn,
                error: $exception->getMessage(),
                exceptionClass: $exception::class,
            ));
            throw $exception;
        }
    }
}
