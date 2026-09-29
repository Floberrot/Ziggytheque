<?php

declare(strict_types=1);

namespace App\Manga\Application\Enrich;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Service\MangaEnricher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * French catalogues describe the book, not the story: a series created from them has
 * no genre nor summary. MyAnimeList fills the gaps, in the background.
 */
#[AsMessageHandler]
final readonly class EnrichMangaHandler
{
    public function __construct(
        private MangaRepositoryInterface $mangaRepository,
        private ExternalApiClientInterface $externalApiClient,
        private MangaEnricher $enricher,
    ) {
    }

    public function __invoke(EnrichMangaMessage $message): void
    {
        $manga = $this->mangaRepository->findById($message->mangaId);
        if ($manga === null || !$this->enricher->needsEnrichment($manga)) {
            return;
        }

        if ($this->enricher->enrich($manga, $this->externalApiClient->searchByTitle($manga->title))) {
            $this->mangaRepository->save($manga);
        }
    }
}
