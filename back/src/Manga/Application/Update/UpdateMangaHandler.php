<?php

declare(strict_types=1);

namespace App\Manga\Application\Update;

use App\Manga\Domain\MangaRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateMangaHandler
{
    public function __construct(private MangaRepositoryInterface $mangaRepository)
    {
    }

    public function __invoke(UpdateMangaCommand $command): void
    {
        $manga = $this->mangaRepository->findById($command->mangaId);

        if ($manga === null) {
            throw new NotFoundException('Manga', $command->mangaId);
        }

        if ($command->title !== null) {
            $manga->title = $command->title;
        }

        if ($command->edition !== null) {
            $manga->edition = $command->edition;
        }

        if ($command->coverUrl !== null) {
            $manga->coverUrl = $command->coverUrl === '' ? null : $command->coverUrl;
        }

        $this->mangaRepository->save($manga);
    }
}
