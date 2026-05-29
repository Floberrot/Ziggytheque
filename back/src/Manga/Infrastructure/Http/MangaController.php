<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use App\Manga\Application\AddVolume\AddVolumeCommand;
use App\Manga\Application\AutoCovers\StartCoverBatchCommand;
use App\Manga\Application\DiscoverEditions\DiscoverEditionsQuery;
use App\Manga\Application\Get\GetMangaQuery;
use App\Manga\Application\Import\ImportMangaCommand;
use App\Manga\Application\PublishScannedIsbn\PublishScannedIsbnCommand;
use App\Manga\Application\Search\SearchMangaQuery;
use App\Manga\Application\SearchExternal\SearchExternalMangaQuery;
use App\Manga\Application\SearchVolumeExternal\SearchVolumeExternalQuery;
use App\Manga\Application\StartScanSession\StartScanSessionCommand;
use App\Manga\Application\Update\UpdateMangaCommand;
use App\Manga\Application\UpdateVolume\UpdateVolumeCommand;
use App\Manga\Domain\Country;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/manga')]
final readonly class MangaController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
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
        $query = $request->query->get('q', '');
        $type  = $request->query->get('type', 'manga');
        $page  = max(1, (int) $request->query->get('page', 1));

        return new JsonResponse($this->queryBus->ask(new SearchExternalMangaQuery($query, $type, $page)));
    }

    #[Route('/editions', methods: ['GET'])]
    public function discoverEditions(Request $request): JsonResponse
    {
        $title   = $request->query->get('title', '');
        $country = Country::fromCode($request->query->get('country'));

        return new JsonResponse($this->queryBus->ask(new DiscoverEditionsQuery($title, $country)));
    }

    /** Composite cover search for individual volume covers/metadata */
    #[Route('/volume-search', methods: ['GET'])]
    public function searchVolumeExternal(Request $request): JsonResponse
    {
        $search       = $request->query->get('q', '');
        $page         = max(1, (int) $request->query->get('page', 1));
        $volumeNumber = $request->query->get('volumeNumber') !== null
            ? (int) $request->query->get('volumeNumber')
            : null;
        $edition      = $request->query->get('edition');
        $provider     = $request->query->get('provider', 'composite');
        $isbn         = $request->query->get('isbn');
        $publisher    = $request->query->get('publisher');
        $year         = $request->query->get('year') !== null
            ? (int) $request->query->get('year')
            : null;
        $language     = $request->query->get('language', 'fr');

        return new JsonResponse($this->queryBus->ask(new SearchVolumeExternalQuery(
            search: $search,
            page: $page,
            volumeNumber: $volumeNumber,
            edition: $edition,
            provider: $provider,
            isbn: $isbn,
            publisher: $publisher,
            year: $year,
            language: $language,
        )));
    }

    #[Route('/scan-session', methods: ['POST'])]
    public function startScanSession(): JsonResponse
    {
        $result = $this->commandBus->dispatch(new StartScanSessionCommand());

        return new JsonResponse($result->toArray(), Response::HTTP_ACCEPTED);
    }

    #[Route('/scan-session/{sessionId}/isbn', methods: ['POST'])]
    public function publishScannedIsbn(
        string $sessionId,
        #[MapRequestPayload] ScanIsbnRequest $request,
    ): JsonResponse {
        $this->commandBus->dispatch(new PublishScannedIsbnCommand(
            sessionId: $sessionId,
            isbn: $request->isbn,
        ));

        return new JsonResponse(null, Response::HTTP_ACCEPTED);
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
            publisher: $request->publisher,
            editionYear: $request->editionYear,
            externalWorkId: $request->externalWorkId,
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
            publisher: $request->publisher,
            editionYear: $request->editionYear,
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
}
