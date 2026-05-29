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

final readonly class OpenLibraryCoversApiClient implements MangaCoverProviderInterface
{
    private const string PREFIX_LOGGER = 'OPEN_LIBRARY : ';
    private const int MIN_COVER_CONTENT_LENGTH = 2000;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private LoggerInterface $logger,
    ) {
    }

    public function findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto
    {
        $coverUrl = sprintf('%s/b/isbn/%s-L.jpg?default=false', $this->baseUrl, $isbn->value);

        $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; BEGIN.', ['isbn' => $isbn->value]);

        try {
            $response = $this->httpClient->request('HEAD', $coverUrl);
            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; NOT FOUND.', [
                    'isbn' => $isbn->value,
                    'status' => $statusCode,
                ]);
                return null;
            }

            $contentLength = (int) ($response->getHeaders()['content-length'][0] ?? 0);
            if ($contentLength < self::MIN_COVER_CONTENT_LENGTH) {
                $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; IMAGE TOO SMALL.', [
                    'isbn' => $isbn->value,
                    'content_length' => $contentLength,
                ]);
                return null;
            }

            $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; FOUND.', ['isbn' => $isbn->value]);

            return new MangaVolumeCoverDto(
                coverUrl: $coverUrl,
                spineUrl: null,
                isbn: $isbn,
                source: 'open_library',
            );
        } catch (Throwable $exception) {
            $this->logger->info(self::PREFIX_LOGGER . 'find by ISBN; ERROR.', [
                'isbn' => $isbn->value,
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    public function findByContext(EditionContext $context, int $volumeNumber): ?MangaVolumeCoverDto
    {
        // Open Library does not support title-based cover search in this context
        return null;
    }
}
