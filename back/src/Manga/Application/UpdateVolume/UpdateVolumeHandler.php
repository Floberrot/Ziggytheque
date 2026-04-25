<?php

declare(strict_types=1);

namespace App\Manga\Application\UpdateVolume;

use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Volume;
use App\Manga\Shared\Event\UpdateVolumeSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateVolumeHandler
{
    public function __construct(
        private MangaRepositoryInterface $mangaRepository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(UpdateVolumeCommand $command): void
    {
        try {
            $manga = $this->mangaRepository->findById($command->mangaId);

            if ($manga === null) {
                throw new NotFoundException('Manga', $command->mangaId);
            }

            $volume = $manga->volumes
                ->filter(fn (Volume $v) => $v->id === $command->volumeId)
                ->first();

            if ($volume === false) {
                throw new NotFoundException('Volume', $command->volumeId);
            }

            if ($command->coverUrl !== null) {
                $volume->coverUrl = $command->coverUrl;
            }

            if ($command->releaseDate !== null) {
                $volume->releaseDate = new DateTimeImmutable($command->releaseDate);
            }

            if ($command->price !== null) {
                $volume->price = $command->price;
            }

            $this->mangaRepository->save($manga);

            $this->eventBus->publish(new UpdateVolumeSucceededEvent(
                mangaId: $manga->id,
                volumeId: $volume->id,
            ));
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
