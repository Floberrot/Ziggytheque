<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use App\Manga\Application\AddVolume\AddVolumeCommand;
use App\Manga\Application\AutoCovers\StartCoverBatchCommand;
use App\Manga\Application\DiscoverEditions\DiscoverEditionsQuery;
use App\Manga\Application\FindCoverByIsbn\FindCoverByIsbnQuery;
use App\Manga\Application\Get\GetMangaQuery;
use App\Manga\Application\GetVolumePrices\GetVolumePricesQuery;
use App\Manga\Application\Import\ImportMangaCommand;
use App\Manga\Application\Search\SearchMangaQuery;
use App\Manga\Application\SearchExternal\SearchExternalMangaQuery;
use App\Manga\Application\SearchVolumeExternal\SearchVolumeExternalQuery;
use App\Manga\Application\TranslateSummary\TranslateSummaryQuery;
use App\Manga\Application\Update\UpdateMangaCommand;
use App\Manga\Application\UpdateVolume\UpdateVolumeCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Infrastructure\RateLimit\CacheRateLimiter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/manga')]
final readonly class MangaController
{
    /** Edition discovery fans out to many catalogues — cap it per client. */
    private const int EDITIONS_RATE_LIMIT = 20;
    private const int EDITIONS_RATE_WINDOW = 60;

    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private CacheRateLimiter $rateLimiter,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');

        return new JsonResponse($this->queryBus->ask(new SearchMangaQuery($query)));
    }

    #[Route('/external', methods: ['GET'])]
    public function searchExternal(Request $request): JsonResponse
    {
        $query    = $request->query->get('q', '');
        $type     = $request->query->get('type', 'manga');
        $page     = max(1, (int) $request->query->get('page', 1));
        $provider = $request->query->get('provider', '');

        return new JsonResponse($this->queryBus->ask(new SearchExternalMangaQuery($query, $type, $page, $provider)));
    }

    #[Route('/cover-by-isbn', methods: ['GET'])]
    public function coverByIsbn(Request $request): JsonResponse
    {
        $isbn = $request->query->get('isbn', '');

        // Grouped result: every source's cover for this ISBN (empty array when none).
        return new JsonResponse($this->queryBus->ask(new FindCoverByIsbnQuery($isbn)));
    }

    /** Composite cover search for individual volume covers/metadata */
    #[Route('/volume-search', methods: ['GET'])]
    public function searchVolumeExternal(Request $request): JsonResponse
    {
        $query        = $request->query->get('q', '');
        $page         = max(1, (int) $request->query->get('page', 1));
        $volumeNumber = $request->query->get('volumeNumber') !== null
            ? (int) $request->query->get('volumeNumber')
            : null;
        $edition      = $request->query->get('edition');
        $provider     = $request->query->get('provider', 'composite');

        return new JsonResponse($this->queryBus->ask(new SearchVolumeExternalQuery(
            search: $query,
            page: $page,
            volumeNumber: $volumeNumber,
            edition: $edition,
            provider: $provider,
        )));
    }

    /** Translate a manga summary into French (English → French for now). */
    #[Route('/translate-summary', methods: ['POST'])]
    public function translateSummary(#[MapRequestPayload] TranslateSummaryRequest $request): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new TranslateSummaryQuery($request->text)));
    }

    /** Discover all editions of a work across sources (BnF, Open Library, Google Books). */
    #[Route('/editions', methods: ['GET'])]
    public function editions(Request $request): JsonResponse
    {
        $this->rateLimiter->consume(
            'manga_editions:' . ($request->getClientIp() ?? 'anon'),
            self::EDITIONS_RATE_LIMIT,
            self::EDITIONS_RATE_WINDOW,
        );

        return new JsonResponse($this->queryBus->ask(new DiscoverEditionsQuery(
            query:    (string) $request->query->get('q', ''),
            author:   $request->query->get('author'),
            language: $request->query->get('language'),
        )));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetMangaQuery($id)));
    }

    #[Route('', methods: ['POST'])]
    public function import(#[MapRequestPayload] ImportMangaRequest $request): JsonResponse
    {
        $id = $this->commandBus->dispatch(new ImportMangaCommand(
            title: $request->title,
            edition: $request->edition,
            language: $request->language,
            author: $request->author,
            summary: $request->summary,
            coverUrl: $request->coverUrl,
            genre: $request->genre,
            externalId: $request->externalId,
            totalVolumes: $request->totalVolumes,
        ));

        return new JsonResponse(['id' => $id], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(string $id, #[MapRequestPayload] UpdateMangaRequest $request): JsonResponse
    {
        $this->commandBus->dispatch(new UpdateMangaCommand(
            mangaId: $id,
            title: $request->title,
            edition: $request->edition,
            coverUrl: $request->coverUrl,
        ));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/volumes', methods: ['POST'])]
    public function addVolume(string $id, #[MapRequestPayload] AddVolumeRequest $request): JsonResponse
    {
        $volumeId = $this->commandBus->dispatch(new AddVolumeCommand(
            mangaId: $id,
            number: $request->number,
            coverUrl: $request->coverUrl,
            releaseDate: $request->releaseDate,
        ));

        return new JsonResponse(['id' => $volumeId], Response::HTTP_CREATED);
    }

    #[Route('/{id}/volumes/{volumeId}', methods: ['PATCH'])]
    public function updateVolume(
        string $id,
        string $volumeId,
        #[MapRequestPayload] UpdateVolumeRequest $request,
    ): JsonResponse {
        $this->commandBus->dispatch(new UpdateVolumeCommand(
            mangaId: $id,
            volumeId: $volumeId,
            coverUrl: $request->coverUrl,
            releaseDate: $request->releaseDate,
            price: $request->price,
            spineUrl: $request->spineUrl,
            isbn: $request->isbn,
        ));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/auto-covers', methods: ['POST'])]
    public function autoCovers(string $id, #[MapRequestPayload] AutoCoversRequest $request): JsonResponse
    {
        $result = $this->commandBus->dispatch(new StartCoverBatchCommand(
            mangaId: $id,
            force: $request->force,
            volumeIds: $request->volumeIds,
        ));

        return new JsonResponse($result->toArray(), Response::HTTP_ACCEPTED);
    }

    /** Discover all editions of a specific manga work (by its persisted title/author). */
    #[Route('/{id}/editions', methods: ['GET'])]
    public function mangaEditions(string $id): JsonResponse
    {
        /** @var array{title?: string, author?: string|null} $manga */
        $manga = $this->queryBus->ask(new GetMangaQuery($id));

        return new JsonResponse($this->queryBus->ask(new DiscoverEditionsQuery(
            query:    $manga['title'] ?? '',
            author:   $manga['author'] ?? null,
            language: null,
        )));
    }

    /** Fetch live price offers for a volume via its ISBN. */
    #[Route('/{id}/volumes/{volumeId}/prices', methods: ['GET'])]
    public function volumePrices(string $id, string $volumeId, Request $request): JsonResponse
    {
        return new JsonResponse($this->queryBus->ask(new GetVolumePricesQuery(
            mangaId:     $id,
            volumeId:    $volumeId,
            marketplace: $request->query->get('marketplace'),
        )));
    }
}
