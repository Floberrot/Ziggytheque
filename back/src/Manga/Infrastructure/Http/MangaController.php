<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use App\Manga\Application\AddVolume\AddVolumeCommand;
use App\Manga\Application\AutoCovers\StartCoverBatchCommand;
use App\Manga\Application\FindCoverByIsbn\FindCoverByIsbnQuery;
use App\Manga\Application\Get\GetMangaQuery;
use App\Manga\Application\GetVolumePrices\GetVolumePricesQuery;
use App\Manga\Application\Import\ImportMangaCommand;
use App\Manga\Application\Search\SearchMangaQuery;
use App\Manga\Application\SearchVolumeExternal\SearchVolumeExternalQuery;
use App\Manga\Application\TranslateSummary\TranslateSummaryQuery;
use App\Manga\Application\Update\UpdateMangaCommand;
use App\Manga\Application\UpdateVolume\UpdateVolumeCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Security\CurrentUserProviderInterface;
use App\Shared\Infrastructure\RateLimit\CacheRateLimiter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/manga')]
final readonly class MangaController
{
    /** Lookups that call outside services (cover sources, translation, price sites). */
    private const int EXTERNAL_LOOKUP_LIMIT  = 30;
    private const int EXTERNAL_LOOKUP_WINDOW = 60;
    /** A cover batch fans out to every tome of the series. */
    private const int COVER_BATCH_LIMIT  = 5;
    private const int COVER_BATCH_WINDOW = 600;

    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private CacheRateLimiter $rateLimiter,
        private CurrentUserProviderInterface $currentUserProvider,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');

        return new JsonResponse($this->queryBus->ask(new SearchMangaQuery($query)));
    }

    #[Route('/cover-by-isbn', methods: ['GET'])]
    public function coverByIsbn(Request $request): JsonResponse
    {
        $this->consumeExternalLookupQuota();
        $isbn = $request->query->get('isbn', '');

        // Grouped result: every source's cover for this ISBN (empty array when none).
        return new JsonResponse($this->queryBus->ask(new FindCoverByIsbnQuery($isbn)));
    }

    /** Composite cover search for individual volume covers/metadata */
    #[Route('/volume-search', methods: ['GET'])]
    public function searchVolumeExternal(Request $request): JsonResponse
    {
        $this->consumeExternalLookupQuota();
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
        $this->consumeExternalLookupQuota();

        return new JsonResponse($this->queryBus->ask(new TranslateSummaryQuery($request->text)));
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
            specialEdition: $request->specialEdition,
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
            specialEdition: $request->specialEdition,
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
            isbn: $request->isbn,
        ));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/auto-covers', methods: ['POST'])]
    public function autoCovers(string $id, #[MapRequestPayload] AutoCoversRequest $request): JsonResponse
    {
        $this->rateLimiter->consume(
            'cover-batch:' . $this->currentUserProvider->currentUserId(),
            self::COVER_BATCH_LIMIT,
            self::COVER_BATCH_WINDOW,
        );

        $result = $this->commandBus->dispatch(new StartCoverBatchCommand(
            mangaId: $id,
            force: $request->force,
            volumeIds: $request->volumeIds,
        ));

        return new JsonResponse($result->toArray(), Response::HTTP_ACCEPTED);
    }

    /** Fetch live price offers for a volume via its ISBN. */
    #[Route('/{id}/volumes/{volumeId}/prices', methods: ['GET'])]
    public function volumePrices(string $id, string $volumeId, Request $request): JsonResponse
    {
        $this->consumeExternalLookupQuota();

        return new JsonResponse($this->queryBus->ask(new GetVolumePricesQuery(
            mangaId:     $id,
            volumeId:    $volumeId,
            marketplace: $request->query->get('marketplace'),
        )));
    }

    /** Per account (see CatalogueController): a looping client cannot hammer the outside services. */
    private function consumeExternalLookupQuota(): void
    {
        $this->rateLimiter->consume(
            'manga-external:' . $this->currentUserProvider->currentUserId(),
            self::EXTERNAL_LOOKUP_LIMIT,
            self::EXTERNAL_LOOKUP_WINDOW,
        );
    }
}
