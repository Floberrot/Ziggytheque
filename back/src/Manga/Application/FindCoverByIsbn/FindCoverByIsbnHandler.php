<?php

declare(strict_types=1);

namespace App\Manga\Application\FindCoverByIsbn;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MultiSourceCoverProviderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class FindCoverByIsbnHandler
{
    public function __construct(
        private MultiSourceCoverProviderInterface $coverProvider,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(FindCoverByIsbnQuery $query): array
    {
        $isbn = Isbn::fromString($query->isbn);

        $covers = [];
        foreach ($this->coverProvider->findAllByIsbn($isbn) as $coverDto) {
            $covers[] = [
                'coverUrl' => $coverDto->coverUrl,
                'spineUrl' => $coverDto->spineUrl,
                'isbn' => $coverDto->isbn?->value,
                'source' => $coverDto->source,
            ];
        }

        return $covers;
    }
}
