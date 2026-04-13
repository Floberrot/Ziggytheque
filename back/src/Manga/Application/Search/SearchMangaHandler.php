<?php

declare(strict_types=1);

namespace App\Manga\Application\Search;

use App\Manga\Domain\MangaRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class SearchMangaHandler
{
    public function __construct(private MangaRepositoryInterface $repository)
    {
    }

    public function __invoke(SearchMangaQuery $query): array
    {
        return array_map(
            static fn ($manga) => $manga->toArray(),
            $this->repository->search($query->query),
        );
    }
}
