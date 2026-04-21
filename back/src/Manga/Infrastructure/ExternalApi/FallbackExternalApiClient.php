<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use App\Manga\Domain\ExternalVolumeDto;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Stateless fallback chain for external manga APIs.
 *
 * Search/detail : MangaDex (primary) → Jikan (fallback)
 * Volume covers : source-aware routing to avoid incoherent cross-provider ID lookups
 *
 *   UUID   → MangaDex covers first, Google Books on failure/empty
 *   int    → skip MangaDex (MAL/Jikan ID), Google Books directly
 *   other  → Google Books directly (Google Books alphanumeric ID)
 */
final readonly class FallbackExternalApiClient implements ExternalApiClientInterface
{
    private const string LOG_PREFIX = '[FallbackChain] ';

    // UUID v4 pattern (MangaDex IDs)
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private MangaDexApiClient $mangaDex,
        private JikanMangaApiClient $jikan,
        private GoogleBooksMangaApiClient $googleBooks,
        private LoggerInterface $logger,
    ) {
    }

    /** @return ExternalMangaDto[] */
    public function searchByTitle(string $query, string $type = 'manga', int $page = 1): array
    {
        $this->logger->info(self::LOG_PREFIX . 'searchByTitle: start', [
            'query' => $query, 'type' => $type, 'page' => $page,
        ]);

        // ① Primary: MangaDex
        try {
            $this->logger->info(self::LOG_PREFIX . 'searchByTitle: trying MangaDex');
            $results = $this->mangaDex->searchByTitle($query, $type, $page);

            if ($results !== []) {
                $this->logger->info(self::LOG_PREFIX . 'searchByTitle: MangaDex OK', [
                    'count' => count($results),
                ]);
                return $results;
            }

            $this->logger->info(self::LOG_PREFIX . 'searchByTitle: MangaDex returned empty — falling back to Jikan', [
                'query' => $query,
            ]);
        } catch (Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . 'searchByTitle: MangaDex threw — falling back to Jikan', [
                'query' => $query,
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
        }

        // ② Fallback: Jikan
        try {
            $this->logger->info(self::LOG_PREFIX . 'searchByTitle: trying Jikan');
            $results = $this->jikan->searchByTitle($query, $type, $page);

            $this->logger->info(self::LOG_PREFIX . 'searchByTitle: Jikan done', [
                'count' => count($results),
            ]);

            return $results;
        } catch (Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . 'searchByTitle: Jikan also threw — returning empty', [
                'query' => $query,
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
            return [];
        }
    }

    public function getMangaById(string $externalId): ?ExternalMangaDto
    {
        $this->logger->info(self::LOG_PREFIX . 'getMangaById: start', ['id' => $externalId]);

        // Only attempt MangaDex if the ID looks like a UUID
        if ($this->isMangaDexId($externalId)) {
            try {
                $this->logger->info(self::LOG_PREFIX . 'getMangaById: trying MangaDex (UUID id)');
                $result = $this->mangaDex->getMangaById($externalId);

                if ($result !== null) {
                    $this->logger->info(self::LOG_PREFIX . 'getMangaById: MangaDex OK', [
                        'id' => $externalId, 'title' => $result->title,
                    ]);
                    return $result;
                }

                $this->logger->info(self::LOG_PREFIX . 'getMangaById: MangaDex returned null — falling back to Jikan', [
                    'id' => $externalId,
                ]);
            } catch (Throwable $e) {
                $this->logger->error(self::LOG_PREFIX . 'getMangaById: MangaDex threw — falling back to Jikan', [
                    'id'    => $externalId,
                    'error' => $e->getMessage(),
                    'class' => $e::class,
                ]);
            }
        } else {
            $this->logger->info(self::LOG_PREFIX . 'getMangaById: non-UUID id, skipping MangaDex', [
                'id' => $externalId,
            ]);
        }

        // ② Fallback: Jikan
        try {
            $this->logger->info(self::LOG_PREFIX . 'getMangaById: trying Jikan');
            $result = $this->jikan->getMangaById($externalId);

            $this->logger->info(self::LOG_PREFIX . 'getMangaById: Jikan done', [
                'id'    => $externalId,
                'found' => $result !== null,
            ]);

            return $result;
        } catch (Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . 'getMangaById: Jikan also threw — returning null', [
                'id'    => $externalId,
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
            return null;
        }
    }

    /** @return ExternalVolumeDto[] */
    public function getVolumeCovers(string $externalId): array
    {
        $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: start', ['id' => $externalId]);

        if ($this->isMangaDexId($externalId)) {
            // UUID → MangaDex first, Google Books on failure/empty
            return $this->getVolumeCoversForMangaDexId($externalId);
        }

        // Integer (MAL/Jikan) or Google Books alphanumeric → Google Books directly
        $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: non-UUID id → Google Books directly', [
            'id'     => $externalId,
            'reason' => $this->isJikanId($externalId) ? 'Jikan/MAL integer id' : 'Google Books id',
        ]);

        return $this->tryGoogleBooksCovers($externalId);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @return ExternalVolumeDto[] */
    private function getVolumeCoversForMangaDexId(string $externalId): array
    {
        // ① MangaDex covers
        try {
            $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: trying MangaDex (UUID id)');
            $covers = $this->mangaDex->getVolumeCovers($externalId);

            if ($covers !== []) {
                $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: MangaDex OK', [
                    'id'      => $externalId,
                    'volumes' => count($covers),
                ]);
                return $covers;
            }

            $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: MangaDex empty — falling back to Google Books', [
                'id' => $externalId,
            ]);
        } catch (Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . 'getVolumeCovers: MangaDex threw — falling back to Google Books', [
                'id'    => $externalId,
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
        }

        // ② Google Books fallback (note: Google Books ID ≠ MangaDex UUID, so covers
        //    will be found by series-name search, not by ID lookup)
        return $this->tryGoogleBooksCovers($externalId);
    }

    /** @return ExternalVolumeDto[] */
    private function tryGoogleBooksCovers(string $externalId): array
    {
        try {
            $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: trying Google Books');
            $covers = $this->googleBooks->getVolumeCovers($externalId);

            $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: Google Books done', [
                'id'      => $externalId,
                'volumes' => count($covers),
            ]);

            return $covers;
        } catch (Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . 'getVolumeCovers: Google Books also threw — returning empty', [
                'id'    => $externalId,
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
            return [];
        }
    }

    private function isMangaDexId(string $id): bool
    {
        return (bool) preg_match(self::UUID_PATTERN, $id);
    }

    private function isJikanId(string $id): bool
    {
        return ctype_digit($id);
    }
}
