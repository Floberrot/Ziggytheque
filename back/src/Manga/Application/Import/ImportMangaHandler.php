<?php

declare(strict_types=1);

namespace App\Manga\Application\Import;

use App\Auth\Domain\UserRepositoryInterface;
use App\Manga\Domain\GenreEnum;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Shared\Event\ImportMangaFailedEvent;
use App\Manga\Shared\Event\ImportMangaStartedEvent;
use App\Manga\Shared\Event\ImportMangaSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Security\CurrentUserProviderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ImportMangaHandler
{
    public function __construct(
        private MangaRepositoryInterface $repository,
        private UserRepositoryInterface $userRepository,
        private CurrentUserProviderInterface $currentUserProvider,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(ImportMangaCommand $command): string
    {
        $started = new ImportMangaStartedEvent(title: $command->title);
        $this->eventBus->publish($started);

        try {
            $manga = new Manga(
                id: Uuid::v4()->toRfc4122(),
                title: $command->title,
                edition: $command->edition,
                language: $command->language,
                author: $command->author,
                summary: $command->summary,
                coverUrl: $command->coverUrl,
                genre: $command->genre !== null ? GenreEnum::from($command->genre) : null,
                externalId: $command->externalId,
                specialEdition: $command->specialEdition,
                // A series typed by hand belongs to whoever typed it.
                owner: $this->userRepository->findById($this->currentUserProvider->currentUserId()),
            );

            // Volume placeholders when the total is known.
            if ($command->totalVolumes !== null && $command->totalVolumes > 0) {
                $manga->ensureVolumesUpTo($command->totalVolumes);
            }

            $this->repository->save($manga);

            $this->eventBus->publish(new ImportMangaSucceededEvent(
                correlationId: $started->correlationId,
                mangaId: $manga->id,
                title: $manga->title,
            ));

            return $manga->id;
        } catch (Throwable $e) {
            $this->eventBus->publish(new ImportMangaFailedEvent(
                correlationId: $started->correlationId,
                title: $command->title,
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
