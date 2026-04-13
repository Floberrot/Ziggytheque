<?php

declare(strict_types=1);

namespace App\Manga\Application\Get;

use App\Manga\Domain\MangaRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetMangaHandler
{
    public function __construct(private MangaRepositoryInterface $repository)
    {
    }

    public function __invoke(GetMangaQuery $query): array
    {
        $manga = $this->repository->findById($query->id);

        if ($manga === null) {
            throw new NotFoundException('Manga', $query->id);
        }

        return $manga->toDetailArray();
    }
}
