<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Http;

use App\Collection\Application\AddFromCatalogue\AddFromCatalogueCommand;
use App\Collection\Application\GetCatalogueEdition\GetCatalogueEditionQuery;
use App\Collection\Application\ScanIsbn\ScanIsbnCommand;
use App\Collection\Application\SearchCatalogue\SearchCatalogueQuery;
use App\Collection\Domain\CatalogueRegistration;
use App\Manga\Domain\Catalogue\CatalogueEdition;
use App\Manga\Domain\Catalogue\CatalogueSearchModeEnum;
use App\Manga\Domain\Catalogue\CatalogueVolume;
use App\Manga\Domain\Isbn;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Security\CurrentUserProviderInterface;
use App\Shared\Infrastructure\RateLimit\CacheRateLimiter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Manga-first entry point: find a tome (title, author or scanned ISBN) in the French
 * catalogues, then add it — the whole series follows.
 */
#[Route('/api/catalogue')]
final readonly class CatalogueController
{
    /**
     * Every call reaches the BnF (and maybe Google Books) — cap it per user. Loose
     * enough for scanning a whole shelf one tome after another.
     */
    private const int CATALOGUE_RATE_LIMIT = 60;
    private const int CATALOGUE_RATE_WINDOW = 60;

    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private CacheRateLimiter $rateLimiter,
        private CurrentUserProviderInterface $currentUserProvider,
    ) {
    }

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->consumeCatalogueQuota();

        $mode = CatalogueSearchModeEnum::tryFrom((string) $request->query->get('mode', ''))
            ?? CatalogueSearchModeEnum::Title;

        return new JsonResponse($this->queryBus->ask(
            new SearchCatalogueQuery((string) $request->query->get('q', ''), $mode),
        ));
    }

    #[Route('/edition', methods: ['GET'])]
    public function edition(Request $request): JsonResponse
    {
        $this->consumeCatalogueQuota();

        return new JsonResponse($this->queryBus->ask(new GetCatalogueEditionQuery(
            workTitle: (string) $request->query->get('workTitle', ''),
            publisher: $this->optionalParameter($request, 'publisher'),
            specialEdition: $this->optionalParameter($request, 'specialEdition'),
        )));
    }

    #[Route('/add', methods: ['POST'])]
    public function add(#[MapRequestPayload] AddFromCatalogueRequest $request): JsonResponse
    {
        $edition = new CatalogueEdition(
            workTitle: trim($request->workTitle),
            publisher: $this->blankToNull($request->publisher),
            specialEdition: $this->blankToNull($request->specialEdition),
            author: $this->blankToNull($request->author),
            coverUrl: $this->blankToNull($request->coverUrl),
            volumeCount: $request->volumeCount,
            volumes: array_map(
                fn (array $volume): CatalogueVolume => new CatalogueVolume(
                    number: $volume['number'],
                    isbn: Isbn::tryFrom($volume['isbn'] ?? null),
                    coverUrl: $this->blankToNull($volume['coverUrl'] ?? null),
                ),
                $request->volumes,
            ),
        );

        /** @var CatalogueRegistration $registration */
        $registration = $this->commandBus->dispatch(new AddFromCatalogueCommand($edition, $request->ownedNumbers));

        return new JsonResponse(
            $registration->toArray(),
            $registration->entryCreated ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }

    #[Route('/scan', methods: ['POST'])]
    public function scan(#[MapRequestPayload] ScanIsbnRequest $request): JsonResponse
    {
        $this->consumeCatalogueQuota();

        return new JsonResponse($this->commandBus->dispatch(new ScanIsbnCommand($request->isbn)));
    }

    /**
     * Keyed on the authenticated user, not the client IP: the app runs behind a proxy
     * without trusted-proxy configuration, so every request reports the same IP.
     */
    private function consumeCatalogueQuota(): void
    {
        $this->rateLimiter->consume(
            'catalogue:' . $this->currentUserProvider->currentUserId(),
            self::CATALOGUE_RATE_LIMIT,
            self::CATALOGUE_RATE_WINDOW,
        );
    }

    private function optionalParameter(Request $request, string $name): ?string
    {
        return $this->blankToNull((string) $request->query->get($name, ''));
    }

    private function blankToNull(?string $value): ?string
    {
        $trimmed = trim($value ?? '');

        return $trimmed === '' ? null : $trimmed;
    }
}
