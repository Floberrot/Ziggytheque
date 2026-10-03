<?php

declare(strict_types=1);

namespace App\Manga\Application\Update;

use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Shared\Event\UpdateMangaFailedEvent;
use App\Manga\Shared\Event\UpdateMangaStartedEvent;
use App\Manga\Shared\Event\UpdateMangaSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateMangaHandler
{
    public function __construct(
        private MangaRepositoryInterface $mangaRepository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(UpdateMangaCommand $command): void
    {
        $started = new UpdateMangaStartedEvent(mangaId: $command->mangaId);
        $this->eventBus->publish($started);

        try {
            $manga = $this->mangaRepository->findById($command->mangaId);

            if ($manga === null) {
                throw new NotFoundException('Manga', $command->mangaId);
            }

            if ($command->title !== null) {
                $manga->title = $command->title;
            }

            if ($command->edition !== null) {
                $manga->edition = $command->edition === '' ? null : $command->edition;
            }

            if ($command->specialEdition !== null) {
                $manga->specialEdition = $command->specialEdition === '' ? null : $command->specialEdition;
            }
            if ($command->coverUrl !== null) {
                $manga->coverUrl = $command->coverUrl === '' ? null : $command->coverUrl;
            }

            $this->mangaRepository->save($manga);

            $this->eventBus->publish(new UpdateMangaSucceededEvent(
                correlationId: $started->correlationId,
                mangaId: $manga->id,
                mangaTitle: $manga->title,
            ));
        } catch (Throwable $exception) {
            $this->eventBus->publish(new UpdateMangaFailedEvent(
                correlationId: $started->correlationId,
                mangaId: $command->mangaId,
                error: $exception->getMessage(),
                exceptionClass: $exception::class,
            ));
            throw $exception;
        }
    }
}
