<?php

declare(strict_types=1);

namespace App\Collection\Application\AddFromCatalogue;

use App\Auth\Domain\UserRepositoryInterface;
use App\Collection\Domain\CatalogueRegistration;
use App\Collection\Domain\Service\CatalogueEntryRegistrar;
use App\Collection\Shared\Event\AddFromCatalogueFailedEvent;
use App\Collection\Shared\Event\AddFromCatalogueStartedEvent;
use App\Collection\Shared\Event\AddFromCatalogueSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Security\CurrentUserProviderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AddFromCatalogueHandler
{
    public function __construct(
        private CatalogueEntryRegistrar $registrar,
        private UserRepositoryInterface $userRepository,
        private CurrentUserProviderInterface $currentUserProvider,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(AddFromCatalogueCommand $command): CatalogueRegistration
    {
        $started = new AddFromCatalogueStartedEvent(
            workTitle: $command->edition->workTitle,
            publisher: $command->edition->publisher,
            specialEdition: $command->edition->specialEdition,
            volumeNumbers: $command->ownedNumbers,
        );
        $this->eventBus->publish($started);

        try {
            $registration = $this->registrar->register(
                $command->edition,
                $command->ownedNumbers,
                $this->userRepository->findById($this->currentUserProvider->currentUserId()),
            );

            $this->eventBus->publish(new AddFromCatalogueSucceededEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $registration->collectionEntryId,
                mangaId: $registration->mangaId,
                workTitle: $command->edition->workTitle,
                seriesCreated: $registration->seriesCreated,
                addedNumbers: $registration->addedNumbers,
            ));

            return $registration;
        } catch (Throwable $exception) {
            $this->eventBus->publish(new AddFromCatalogueFailedEvent(
                correlationId: $started->correlationId,
                workTitle: $command->edition->workTitle,
                error: $exception->getMessage(),
                exceptionClass: $exception::class,
            ));
            throw $exception;
        }
    }
}
