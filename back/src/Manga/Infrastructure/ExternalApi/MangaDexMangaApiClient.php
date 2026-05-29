<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\EditionContext;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaVolumeCoverDto;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class MangaDexMangaApiClient implements MangaCoverProviderInterface
{
    private const string PREFIX_LOGGER = 'MANGADEX : ';
    private const string UPLOADS_BASE_URL = 'https://uploads.mangadex.org/covers';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private LoggerInterface $logger,
    ) {
    }

    public function findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto
    {
        // MangaDex does not expose ISBN-based search
        return null;
    }

    public function findByContext(EditionContext $context, int $volumeNumber): ?MangaVolumeCoverDto
    {
        $this->logger->info(self::PREFIX_LOGGER . 'find by context; BEGIN.', [
            'title'    => $context->mangaTitle,
            'volume'   => $volumeNumber,
            'language' => $context->language,
        ]);

        try {
            $mangaId = $this->searchMangaId($context->mangaTitle, $context->language);

            if ($mangaId === null) {
                $this->logger->info(
                    self::PREFIX_LOGGER . 'find by context; NO MANGA FOUND.',
                    ['title' => $context->mangaTitle],
                );
                return null;
            }

            $coverDto = $this->findVolumeCover($mangaId, $volumeNumber, $context->language);

            if ($coverDto === null) {
                $this->logger->info(self::PREFIX_LOGGER . 'find by context; NO COVER FOUND.', [
                    'title' => $context->mangaTitle,
                    'manga_id' => $mangaId,
                    'volume' => $volumeNumber,
                ]);
            }

            return $coverDto;
        } catch (Throwable $exception) {
            $this->logger->info(self::PREFIX_LOGGER . 'find by context; ERROR.', [
                'title' => $context->mangaTitle,
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    private function searchMangaId(string $mangaTitle, string $language): ?string
    {
        $response = $this->httpClient->request('GET', $this->baseUrl . '/manga', [
            'query' => [
                'title' => $mangaTitle,
                'availableTranslatedLanguage[]' => $language,
                'limit' => 5,
            ],
        ]);

        $data = $response->toArray();
        $results = $data['data'] ?? [];

        return $results[0]['id'] ?? null;
    }

    private function findVolumeCover(string $mangaId, int $volumeNumber, string $language): ?MangaVolumeCoverDto
    {
        $response = $this->httpClient->request('GET', $this->baseUrl . '/cover', [
            'query' => [
                'manga[]' => $mangaId,
                'locales[]' => $language,
                'limit' => 100,
                'order[volume]' => 'asc',
            ],
        ]);

        $data = $response->toArray();
        $covers = $data['data'] ?? [];

        $primaryCoverUrl = null;
        $spineUrl = null;

        foreach ($covers as $cover) {
            $attributes = $cover['attributes'] ?? [];
            $coverVolume = $attributes['volume'] ?? null;

            if ($coverVolume !== (string) $volumeNumber) {
                continue;
            }

            $fileName = $attributes['fileName'] ?? null;
            if ($fileName === null) {
                continue;
            }

            $coverUrl = sprintf('%s/%s/%s', self::UPLOADS_BASE_URL, $mangaId, $fileName);

            if ($primaryCoverUrl === null) {
                $primaryCoverUrl = $coverUrl;
            } else {
                // A second cover for the same volume can be the back/spine image
                $spineUrl = $coverUrl;
                break;
            }
        }

        if ($primaryCoverUrl === null) {
            return null;
        }

        $this->logger->info(self::PREFIX_LOGGER . 'find by context; FOUND.', [
            'manga_id' => $mangaId,
            'volume' => $volumeNumber,
        ]);

        return new MangaVolumeCoverDto(
            coverUrl: $primaryCoverUrl,
            spineUrl: $spineUrl,
            isbn: null,
            source: 'mangadex',
        );
    }
}
