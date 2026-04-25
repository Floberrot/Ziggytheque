<?php

declare(strict_types=1);

namespace App\Manga\Application\AddVolume;

use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Volume;
use App\Manga\Shared\Event\AddVolumeFailedEvent;
use App\Manga\Shared\Event\AddVolumeStartedEvent;
use App\Manga\Shared\Event\AddVolumeSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AddVolumeHandler
{
    public function __construct(
        private MangaRepositoryInterface $mangaRepository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(AddVolumeCommand $command): string
    {
        $started = new AddVolumeStartedEvent(
            mangaId: $command->mangaId,
            number: $command->number,
        );
        $this->eventBus->publish($started);

        try {
            $manga = $this->mangaRepository->findById($command->mangaId);

            if ($manga === null) {
                throw new NotFoundException('Manga', $command->mangaId);
            }

            $volume = new Volume(
                id: Uuid::v4()->toRfc4122(),
                manga: $manga,
                number: $command->number,
                coverUrl: $command->coverUrl,
                releaseDate: $command->releaseDate !== null
                    ? new DateTimeImmutable($command->releaseDate)
                    : null,
            );

            $manga->addVolume($volume);
            $this->mangaRepository->save($manga);

            $this->eventBus->publish(new AddVolumeSucceededEvent(
                correlationId: $started->correlationId,
                mangaId: $manga->id,
                volumeId: $volume->id,
                number: $volume->number,
            ));

            return $volume->id;
        } catch (Throwable $e) {
            $this->eventBus->publish(new AddVolumeFailedEvent(
                correlationId: $started->correlationId,
                mangaId: $command->mangaId,
                number: $command->number,
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
