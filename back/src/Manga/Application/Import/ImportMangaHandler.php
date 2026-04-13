<?php

declare(strict_types=1);

namespace App\Manga\Application\Import;

use App\Manga\Domain\GenreEnum;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ImportMangaHandler
{
    public function __construct(private MangaRepositoryInterface $repository)
    {
    }

    public function __invoke(ImportMangaCommand $command): string
    {
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
        );

        $this->repository->save($manga);

        return $manga->id;
    }
}
